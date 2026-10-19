<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestFile;
use App\Models\ServiceRequestItem;
use App\Models\ServiceRequestShipment;
use App\Support\AdminAccess;
use App\Support\Audit;
use App\Support\Notify;
use App\Support\WarrantyPolicy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * All the rules of a claim (exchange / wrong product / defective goods) in one place.
 *
 *  - The original order and its invoice are never edited.
 *  - A replacement or exchange is a NEW order made by AdminOrderBuilder, so stock, the Steadfast "Send" button
 *    and the invoice work exactly as for any order (one courier booking per order, no double booking).
 *  - Money given back is a normal refund row in "payments" (so the accounts follow), linked to the claim.
 *  - Goods that come back are NEVER put back on the shelf automatically. At inspection a person decides:
 *    restock / return to supplier / scrap. Only "restock" adds stock.
 *
 * Messages of RuntimeException / DomainException are meant to be shown to the admin.
 */
class ClaimService
{
    const MAX_FILES  = 12;
    const IMAGE_MAX  = 5 * 1024 * 1024;   // 5 MB
    const VIDEO_MAX  = 30 * 1024 * 1024;  // 30 MB

    const IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    const VIDEO_TYPES = ['video/mp4' => 'mp4', 'video/quicktime' => 'mov', 'video/webm' => 'webm'];

    private AdminOrderBuilder $builder;

    public function __construct(AdminOrderBuilder $builder)
    {
        $this->builder = $builder;
    }

    // ------------------------------------------------------------------ open

    /**
     * @param array $data     type, reason, return_required
     * @param array $qtys     [order_item_id => quantity]
     * @param array $received [order_item_id => product id actually received] (wrong product only, optional)
     */
    public function open(Order $order, array $data, array $qtys, array $received = []): ServiceRequest
    {
        $this->assertReady();

        $lines = [];
        foreach ($qtys as $itemId => $qty) {
            if ((int) $qty > 0) {
                $lines[(int) $itemId] = (int) $qty;
            }
        }
        if (!$lines) {
            throw new \RuntimeException('Please tick at least one product and enter how many pieces the claim is for.');
        }
        if (!array_key_exists($data['type'] ?? '', ServiceRequest::types())) {
            throw new \RuntimeException('Please choose the type of claim.');
        }

        $claim = DB::transaction(function () use ($order, $data, $lines, $received) {
            // Lock the order so two people cannot claim the same pieces at the same moment
            $o = Order::with('items')->lockForUpdate()->findOrFail($order->id);

            if ($o->status !== 'delivered') {
                throw new \RuntimeException('A claim can only be opened for a delivered order (order #' . $o->id . ' is ' . strtolower($o->statusLabel()) . ').');
            }

            $used = ServiceRequestItem::query()
                ->join('service_requests', 'service_requests.id', '=', 'service_request_items.service_request_id')
                ->where('service_requests.order_id', $o->id)
                ->where(function ($w) {
                    // a finished warranty claim frees its pieces (the unit can fail again inside the warranty)
                    $w->where(function ($x) {
                        $x->where('service_requests.type', '!=', 'warranty')->whereIn('service_requests.status', ServiceRequest::activeStatuses());
                    })->orWhere(function ($x) {
                        $x->where('service_requests.type', 'warranty')->whereIn('service_requests.status', ['open', 'approved']);
                    });
                })
                ->selectRaw('service_request_items.order_item_id as oi, SUM(service_request_items.quantity) as q')
                ->groupBy('service_request_items.order_item_id')
                ->pluck('q', 'oi');

            $rows = [];
            foreach ($lines as $itemId => $qty) {
                $line = $o->items->firstWhere('id', $itemId);
                if (!$line) {
                    throw new \RuntimeException('One of the products is not part of order #' . $o->id . '.');
                }
                $left = (int) $line->quantity - (int) ($used[$itemId] ?? 0);
                if ($qty > $left) {
                    throw new \RuntimeException('"' . $line->title . '": only ' . max(0, $left) . ' piece(s) can still be claimed (ordered ' . (int) $line->quantity . ', already in other claims ' . (int) ($used[$itemId] ?? 0) . ').');
                }

                $recvId = !empty($received[$itemId]) ? (int) $received[$itemId] : null;
                if ($recvId && !Product::whereKey($recvId)->exists()) {
                    throw new \RuntimeException('Product ID ' . $recvId . ' (product received) does not exist.');
                }

                $rows[] = [
                    'order_item_id'       => $line->id,
                    'product_id'          => $line->product_id,
                    'received_product_id' => $recvId && $recvId !== (int) $line->product_id ? $recvId : null,
                    'title'               => $line->title,
                    'price'               => (int) $line->price,
                    'quantity'            => $qty,
                ];
            }

            $staff = AdminAccess::staff();

            $claim = ServiceRequest::create([
                'order_id'        => $o->id,
                'customer_name'   => $o->name,
                'customer_phone'  => $o->phone,
                'type'            => $data['type'],
                'status'          => 'open',
                'reason'          => $data['reason'] ?? null,
                'return_required' => !empty($data['return_required']),
                'opened_by_id'    => $staff ? $staff->id : null,
                'opened_by_name'  => AdminAccess::name(),
            ]);
            $claim->items()->createMany($rows);

            return $claim;
        });

        $this->log('claim.opened', $claim, 'Claim #' . $claim->id . ' opened for order #' . $claim->order_id . ' (' . $claim->typeLabel() . ')', null, [
            'order_id' => $claim->order_id, 'type' => $claim->type, 'items' => count($lines), 'return_required' => $claim->return_required,
        ]);

        return $claim;
    }

    // ------------------------------------------------------------------ decide

    public function approve(ServiceRequest $claim, string $resolution, ?string $note): ServiceRequest
    {
        $this->refuseWarranty($claim, 'accepted');
        if (!array_key_exists($resolution, ServiceRequest::resolutions())) {
            throw new \RuntimeException('Please choose how the claim will be solved: replacement, exchange or refund.');
        }

        return $this->decide($claim, 'approved', $resolution, $note);
    }

    public function reject(ServiceRequest $claim, ?string $note): ServiceRequest
    {
        $this->refuseWarranty($claim, 'rejected');
        if (trim((string) $note) === '') {
            throw new \RuntimeException('Please write why the claim is rejected.');
        }

        return $this->decide($claim, 'rejected', null, $note);
    }

    private function decide(ServiceRequest $claim, string $status, ?string $resolution, ?string $note): ServiceRequest
    {
        DB::transaction(function () use (&$claim, $status, $resolution, $note) {
            $claim = ServiceRequest::lockForUpdate()->findOrFail($claim->id);
            if ($claim->status !== 'open') {
                throw new \RuntimeException('Claim #' . $claim->id . ' is already ' . strtolower($claim->statusLabel()) . '.');
            }

            $staff = AdminAccess::staff();
            $claim->update([
                'status'          => $status,
                'resolution'      => $resolution,
                'decision_note'   => $note,
                'decided_by_id'   => $staff ? $staff->id : null,
                'decided_by_name' => AdminAccess::name(),
                'decided_at'      => now(),
            ]);
        });

        $this->log('claim.' . $status, $claim, 'Claim #' . $claim->id . ' ' . $status . ($resolution ? ' (' . $resolution . ')' : ''), ['status' => 'open'], ['status' => $status, 'resolution' => $resolution]);

        return $claim;
    }

    // ------------------------------------------------------------------ replacement / exchange order

    /**
     * Makes the linked new order. Replacement = same goods free of charge; exchange = goods at today's price
     * minus the value of the goods that come back.
     *
     * @param array    $lines    [product_id => quantity]
     * @param int|null $credit   exchange only: value of the returned goods taken off (never more than the claimed goods are worth)
     * @param int      $delivery delivery charge of the new order in taka (0 = we pay)
     * @return array [Order, bool created]
     */
    public function createOrder(ServiceRequest $claim, array $lines, ?int $credit, int $delivery, string $token): array
    {
        $this->assertReady();

        return DB::transaction(function () use ($claim, $lines, $credit, $delivery, $token) {
            $claim = ServiceRequest::with('items')->lockForUpdate()->findOrFail($claim->id);

            if ($claim->status !== 'approved' || !in_array($claim->resolution, ['replacement', 'exchange'], true)) {
                throw new \RuntimeException('Claim #' . $claim->id . ' is not approved for a replacement or exchange.');
            }
            if ($claim->replacement_order_id) {
                throw new \RuntimeException('Claim #' . $claim->id . ' already has order #' . $claim->replacement_order_id . '.');
            }

            $original = Order::findOrFail($claim->order_id);
            $exchange = $claim->resolution === 'exchange';

            if ($claim->type === 'warranty') {
                // Free goods are only promised for what was claimed: the same product(s), the same number of pieces
                $expected = [];
                foreach ($claim->items as $it) {
                    $expected[(int) $it->product_id] = ($expected[(int) $it->product_id] ?? 0) + (int) $it->quantity;
                }
                $given = [];
                foreach ($lines as $pid => $q) {
                    $given[(int) $pid] = (int) $q;
                }
                ksort($expected);
                ksort($given);
                if ($expected !== $given) {
                    throw new \RuntimeException('A warranty replacement must be the same product and the same number of pieces as the claim.');
                }
            }
            $credit   = $exchange ? min(max(0, (int) $credit), $claim->itemsValue()) : 0;

            $note = ucfirst($claim->resolution) . ' for order #' . $original->id . ' (claim #' . $claim->id . ')'
                . ($exchange && $credit > 0 ? ' - returned goods credited: ' . number_format($credit) . ' taka' : '');

            [$order, $created] = $this->builder->create([
                'name'               => $original->name,
                'phone'              => $original->phone,
                'district'           => $original->district,
                'upazila'            => $original->upazila,
                'city'               => $original->city,
                'address_line'       => $original->address,
                'note'               => $note,
                'client_token'       => $token,
                'source_order_id'    => $original->id,
                'address_id'         => $original->address_id,
                'order_type'         => $claim->resolution,
                'service_request_id' => $claim->id,
                'free_items'         => !$exchange,
                'credit'             => $credit,
                'delivery_override'  => max(0, $delivery),
                'allow_unpublished'  => true,
            ], $lines);

            if ($created) {
                // The replacement unit continues the ORIGINAL warranty (a new claim on the original order line stays possible
                // until that warranty ends), so the new order line does not start a second warranty period.
                if ($claim->type === 'warranty' && WarrantyPolicy::columnsReady()) {
                    OrderItem::where('order_id', $order->id)->update([
                        'warranty_type' => 'none', 'warranty_days' => null, 'warranty_terms' => null, 'warranty_source' => 'continued',
                    ]);
                }
                $claim->update(['replacement_order_id' => $order->id, 'exchange_credit' => $credit]);
                $this->log('claim.order_created', $claim, ucfirst($claim->resolution) . ' order #' . $order->id . ' made from claim #' . $claim->id, null, [
                    'order_id' => $order->id, 'total' => $order->total, 'credit' => $credit,
                ]);
            }

            return [$order, $created];
        });
    }

    /** Exchange: money still to give back because the returned goods are worth more than the new goods */
    public function exchangeExcess(ServiceRequest $claim): int
    {
        if ($claim->resolution !== 'exchange' || !$claim->replacement_order_id) {
            return 0;
        }
        $new = Order::find($claim->replacement_order_id);
        if (!$new) {
            return 0;
        }

        return max(0, (int) $claim->exchange_credit - (int) $new->subtotal);
    }

    // ------------------------------------------------------------------ refund

    /** Records a refund on the ORIGINAL order, linked to the claim. It goes through the normal payments / accounts flow. */
    public function refund(ServiceRequest $claim, int $amount, string $method, ?string $trxId, ?string $note): Payment
    {
        $this->assertReady();
        if ($claim->type === 'warranty') {
            throw new \RuntimeException('A warranty claim is solved by repair or replacement, not by a refund.');
        }

        if ($amount < 1) {
            throw new \RuntimeException('Please enter the refund amount.');
        }
        if (!array_key_exists($method, Payment::methods())) {
            throw new \RuntimeException('Please choose a refund method.');
        }
        $trxId = $trxId !== null && trim($trxId) !== '' ? trim($trxId) : null;
        if ($trxId !== null && Payment::where('trx_id', $trxId)->exists()) {
            throw new \RuntimeException('This transaction ID has already been recorded.');
        }

        $payment = DB::transaction(function () use ($claim, $amount, $method, $trxId, $note) {
            $claim = ServiceRequest::lockForUpdate()->findOrFail($claim->id);
            if ($claim->status !== 'approved') {
                throw new \RuntimeException('Refunds can only be recorded for an approved claim that is not closed yet.');
            }

            // Lock the order so the same money cannot be refunded twice at the same moment
            $order = Order::lockForUpdate()->findOrFail($claim->order_id);
            $held  = $order->moneyHeld();
            if ($amount > $held) {
                throw new \RuntimeException('You can refund at most ৳ ' . number_format(max(0, $held)) . ' for order #' . $order->id . ' (the money received so far). If the courier has not paid yet, record "cash on delivery collected" on the order first.');
            }

            $payment = Payment::create([
                'order_id'           => $order->id,
                'type'               => 'refund',
                'method'             => $method,
                'amount'             => $amount,
                'trx_id'             => $trxId,
                'note'               => trim('Claim #' . $claim->id . ($note ? ': ' . $note : '')),
                'paid_at'            => now(),
                'service_request_id' => $claim->id,
            ]);
            Notify::payment($payment->setRelation('order', $order));

            return $payment;
        });

        $this->log('claim.refund', $claim, 'Refund ৳ ' . number_format($amount) . ' for claim #' . $claim->id . ' (order #' . $claim->order_id . ')', null, [
            'amount' => $amount, 'method' => $method, 'payment_id' => $payment->id,
        ]);

        return $payment;
    }

    // ------------------------------------------------------------------ inspection

    /**
     * Goods that came back are looked at and sorted. Per product line three numbers that add up to the claimed
     * quantity: back to stock / return to supplier / scrap. ONLY the "restock" number adds stock.
     *
     * @param array $rows [claim item id => ['restock' => n, 'supplier' => n, 'scrap' => n, 'note' => text]]
     * @return int number of lines that were inspected now
     */
    public function inspect(ServiceRequest $claim, array $rows): int
    {
        $this->assertReady();
        if ($claim->type === 'warranty') {
            throw new \RuntimeException('A warranty claim is inspected on its warranty panel. A defective unit is never put back on the shelf.');
        }

        $done = DB::transaction(function () use ($claim, $rows) {
            $claim = ServiceRequest::with('items')->lockForUpdate()->findOrFail($claim->id);

            if ($claim->status !== 'approved') {
                throw new \RuntimeException('Only an approved claim can be inspected.');
            }
            if (!$claim->return_required) {
                throw new \RuntimeException('This claim does not need the goods back, so there is nothing to inspect.');
            }

            $count  = 0;
            $staff  = AdminAccess::name();
            $stocked = [];

            foreach ($claim->items as $item) {
                $r = $rows[$item->id] ?? null;
                if (!$r) {
                    continue;
                }
                $restock  = max(0, (int) ($r['restock'] ?? 0));
                $supplier = max(0, (int) ($r['supplier'] ?? 0));
                $scrap    = max(0, (int) ($r['scrap'] ?? 0));
                if ($restock + $supplier + $scrap === 0) {
                    continue; // left empty: inspect it later
                }
                if ($item->isInspected()) {
                    throw new \RuntimeException('"' . $item->title . '" was already inspected and cannot be changed.');
                }
                if ($restock + $supplier + $scrap !== (int) $item->quantity) {
                    throw new \RuntimeException('"' . $item->title . '": the three numbers must add up to ' . (int) $item->quantity . ' (they add up to ' . ($restock + $supplier + $scrap) . ').');
                }

                if ($restock > 0) {
                    $pid     = $item->stockProductId();
                    $product = $pid ? Product::whereKey($pid)->lockForUpdate()->first() : null;
                    if (!$product) {
                        throw new \RuntimeException('"' . $item->title . '": the product no longer exists, so it cannot go back to stock. Choose supplier return or scrap.');
                    }
                    $product->increment('stock_quantity', $restock);
                    $stocked[$product->id] = $product->id;
                }

                $item->update([
                    'qty_restock'       => $restock,
                    'qty_supplier'      => $supplier,
                    'qty_scrap'         => $scrap,
                    'inspected_at'      => now(),
                    'inspected_by_name' => $staff,
                    'inspection_note'   => isset($r['note']) && trim((string) $r['note']) !== '' ? Str::limit(trim((string) $r['note']), 250, '') : null,
                ]);
                $count++;

                $this->log('claim.inspected', $claim, 'Claim #' . $claim->id . ' "' . $item->title . '": restock ' . $restock . ', supplier ' . $supplier . ', scrap ' . $scrap, null, [
                    'item_id' => $item->id, 'restock' => $restock, 'supplier' => $supplier, 'scrap' => $scrap,
                ]);
            }

            if ($count === 0) {
                throw new \RuntimeException('Nothing to save: enter the numbers for at least one product.');
            }

            return ['count' => $count, 'stocked' => array_values($stocked)];
        });

        foreach (Product::whereIn('id', $done['stocked'])->get() as $p) {
            Notify::stockRecovered($p);
        }

        return $done['count'];
    }

    // ------------------------------------------------------------------ close

    /** Why the claim cannot be closed yet (null = it can) */
    public function closeProblem(ServiceRequest $claim): ?string
    {
        if ($claim->type === 'warranty') {
            return (new WarrantyService())->closeProblem($claim);
        }

        $claim->loadMissing('items');

        if ($claim->status !== 'approved') {
            return 'Only an approved claim can be closed.';
        }
        if (in_array($claim->resolution, ['replacement', 'exchange'], true) && !$claim->replacement_order_id) {
            return 'Make the ' . $claim->resolution . ' order first.';
        }
        if ($claim->resolution === 'refund' && $claim->refundedAmount() <= 0) {
            return 'Record the refund first.';
        }
        if ($claim->return_required && !$claim->fullyInspected()) {
            return 'Inspect the returned goods first (choose what happens to every product).';
        }

        return null;
    }

    public function close(ServiceRequest $claim): ServiceRequest
    {
        DB::transaction(function () use (&$claim) {
            $claim = ServiceRequest::with('items')->lockForUpdate()->findOrFail($claim->id);
            if ($problem = $this->closeProblem($claim)) {
                throw new \RuntimeException($problem);
            }
            $claim->update(['status' => 'resolved', 'closed_at' => now(), 'closed_by_name' => AdminAccess::name()]);
        });

        $this->log('claim.closed', $claim, 'Claim #' . $claim->id . ' resolved', ['status' => 'approved'], ['status' => 'resolved']);

        return $claim;
    }

    // ------------------------------------------------------------------ evidence (private files)

    /** Returns a list of problems with the uploaded files (empty = all fine). Call this BEFORE saving anything. */
    public function checkFiles(array $files, int $alreadyStored = 0): array
    {
        $files = array_values(array_filter($files, fn ($f) => $f instanceof UploadedFile));
        $errors = [];

        if ($alreadyStored + count($files) > self::MAX_FILES) {
            $errors[] = 'A claim can have at most ' . self::MAX_FILES . ' files (' . $alreadyStored . ' already saved).';
        }

        foreach ($files as $f) {
            $name = $f->getClientOriginalName();
            if (!$f->isValid()) {
                $errors[] = '"' . $name . '" could not be uploaded (' . $f->getErrorMessage() . ').';
                continue;
            }
            [$kind] = $this->classify($f);
            if (!$kind) {
                $errors[] = '"' . $name . '" is not allowed. Use JPG, PNG or WebP photos, or MP4, MOV or WebM videos.';
                continue;
            }
            $max = $kind === 'image' ? self::IMAGE_MAX : self::VIDEO_MAX;
            if ($f->getSize() > $max) {
                $errors[] = '"' . $name . '" is too big (photos up to ' . (self::IMAGE_MAX / 1048576) . ' MB, videos up to ' . (self::VIDEO_MAX / 1048576) . ' MB).';
            }
        }

        return $errors;
    }

    /** @return array [kind|null, extension|null] decided from the real content of the file, not from its name */
    private function classify(UploadedFile $f): array
    {
        $mime = (string) $f->getMimeType();

        if (isset(self::IMAGE_TYPES[$mime])) {
            // a real picture, not just a file that says so
            if (@getimagesize($f->getRealPath()) === false) {
                return [null, null];
            }

            return ['image', self::IMAGE_TYPES[$mime]];
        }
        if (isset(self::VIDEO_TYPES[$mime])) {
            return ['video', self::VIDEO_TYPES[$mime]];
        }

        return [null, null];
    }

    /** Saves the files in storage/app/claims/{id}/ (private). Returns how many were saved. */
    public function addEvidence(ServiceRequest $claim, array $files, ?string $note = null): int
    {
        $files = array_values(array_filter($files, fn ($f) => $f instanceof UploadedFile));
        $saved = 0;

        foreach ($files as $f) {
            [$kind, $ext] = $this->classify($f);
            if (!$kind) {
                continue;
            }
            $mime = (string) $f->getMimeType();
            $size = (int) $f->getSize();
            $name = Str::random(40) . '.' . $ext;                       // never the customer's / admin's file name
            $path = Storage::disk('local')->putFileAs('claims/' . $claim->id, $f, $name);
            if (!$path) {
                continue;
            }

            ServiceRequestFile::create([
                'service_request_id' => $claim->id,
                'path'               => $path,
                'original_name'      => Str::limit(preg_replace('/[^\p{L}\p{N}._ -]/u', '_', $f->getClientOriginalName()), 180, ''),
                'mime'               => $mime,
                'size'               => $size,
                'kind'               => $kind,
                'note'               => $note ? Str::limit($note, 250, '') : null,
                'uploaded_by_name'   => AdminAccess::name(),
            ]);
            $saved++;
        }

        if ($saved > 0) {
            $this->log('claim.evidence_added', $claim, $saved . ' evidence file(s) added to claim #' . $claim->id, null, ['files' => $saved]);
        }

        return $saved;
    }

    public function deleteEvidence(ServiceRequestFile $file): void
    {
        $claim = ServiceRequest::find($file->service_request_id);
        Storage::disk('local')->delete($file->path);
        $file->delete();

        if ($claim) {
            $this->log('claim.evidence_deleted', $claim, 'Evidence file removed from claim #' . $claim->id, ['name' => $file->original_name], null);
        }
    }

    // ------------------------------------------------------------------ shipments

    public function addShipment(ServiceRequest $claim, array $d): ServiceRequestShipment
    {
        $ship = $claim->shipments()->create([
            'direction'       => $d['direction'],
            'courier'         => $d['courier'] ?? null,
            'tracking_code'   => $d['tracking_code'] ?? null,
            'status'          => $d['status'],
            'shipped_at'      => !empty($d['shipped_at']) ? $d['shipped_at'] : null,
            'received_at'     => $d['status'] === 'received' ? (!empty($d['received_at']) ? $d['received_at'] : now()) : null,
            'note'            => $d['note'] ?? null,
            'created_by_name' => AdminAccess::name(),
        ]);

        $this->log('claim.shipment_added', $claim, 'Shipment added to claim #' . $claim->id . ' (' . $ship->direction . ', ' . $ship->status . ')', null, $ship->only(['direction', 'courier', 'tracking_code', 'status']));

        return $ship;
    }

    public function updateShipment(ServiceRequestShipment $ship, string $status, ?string $note): ServiceRequestShipment
    {
        $old = $ship->status;
        $ship->update([
            'status'      => $status,
            'received_at' => $status === 'received' ? ($ship->received_at ?: now()) : null,
            'note'        => $note !== null && trim($note) !== '' ? $note : $ship->note,
        ]);

        $claim = ServiceRequest::find($ship->service_request_id);
        if ($claim) {
            $this->log('claim.shipment_updated', $claim, 'Shipment of claim #' . $claim->id . ': ' . $old . ' → ' . $status, ['status' => $old], ['status' => $status]);
        }

        return $ship;
    }

    // ------------------------------------------------------------------ helpers

    /** Warranty claims have their own rules (policy, inspection, allowed solutions): the exchange screens must not decide them */
    private function refuseWarranty(ServiceRequest $claim, string $what): void
    {
        if ($claim->type === 'warranty') {
            throw new \RuntimeException('A warranty claim is ' . $what . ' on its warranty panel (it checks the warranty policy of the purchase).');
        }
    }

    private function assertReady(): void
    {
        if (!ServiceRequest::tableReady()) {
            throw new \RuntimeException('The claim tables are missing. Run "php artisan migrate" first.');
        }
    }

    private function log(string $action, ServiceRequest $claim, string $summary, ?array $old = null, ?array $new = null): void
    {
        Audit::log($action, 'claim', $claim->id, $summary, $old, $new, $claim->customer_phone);
    }
}
