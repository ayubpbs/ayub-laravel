<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ServiceRequest;
use App\Support\AdminAccess;
use App\Support\Audit;
use App\Support\WarrantyPolicy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Warranty claims. A warranty claim is a ServiceRequest with type "warranty" for ONE order line, so evidence,
 * shipments, history, replacement orders and closing all reuse the Phase 5 claim screens.
 *
 * Flow:  open (checks the warranty) -> accept for inspection (or reject) -> product received (inbound shipment)
 *        -> inspection -> resolution (repair / replacement, only what the policy allows) -> dispatch -> close.
 *
 *  - The original order, its invoice and its prices are never edited.
 *  - The policy used is a snapshot taken from the order line (or, for old lines, from the product today, marked "backfilled").
 *  - Free repair / free replacement is only possible when the policy of that purchase allows it.
 *  - The defective unit that comes back is never put on the shelf: no stock is added here.
 *
 * Messages of RuntimeException are meant to be shown to the admin.
 */
class WarrantyService
{
    // ------------------------------------------------------------------ eligibility

    /**
     * What the warranty of one order line looks like right now.
     *
     * @return array policy [type, days, terms, source], start (Carbon|null), start_source, expires, state, days_left, message
     */
    public function assess(Order $order, OrderItem $line, ?string $adminStart = null, ?Carbon $at = null): array
    {
        $at = $at ?: now();

        if (!empty($line->warranty_source)) {
            $policy = [
                'type'   => (string) ($line->warranty_type ?: 'none'),
                'days'   => (int) $line->warranty_days,
                'terms'  => $line->warranty_terms,
                'source' => (string) $line->warranty_source,
            ];
        } else {
            // An old line has no snapshot: use the product's policy as it is today and say so
            $product = $line->product_id ? \App\Models\Product::find($line->product_id) : null;
            $policy  = WarrantyPolicy::forProduct($product);
            $policy['source'] = 'backfilled';
        }

        $start       = null;
        $startSource = null;
        if (!empty($order->delivered_at)) {
            $start       = Carbon::parse($order->delivered_at);
            $startSource = 'delivered';
        } elseif ($adminStart !== null && trim($adminStart) !== '') {
            try {
                $d = Carbon::parse($adminStart);
            } catch (\Throwable $e) {
                throw new \RuntimeException('The delivery date is not a valid date.');
            }
            if ($d->gt(now()->endOfDay())) {
                throw new \RuntimeException('The delivery date cannot be in the future.');
            }
            if ($d->copy()->endOfDay()->lt(Carbon::parse($order->created_at)->startOfDay())) {
                throw new \RuntimeException('The delivery date cannot be before the order date (' . Carbon::parse($order->created_at)->format('d M Y') . ').');
            }
            $start       = $d;
            $startSource = 'admin';
        }

        return ['policy' => $policy, 'start' => $start, 'start_source' => $startSource] + WarrantyPolicy::evaluate($policy, $start, $at);
    }

    /** Pieces of a line that are still free for a warranty claim */
    public function piecesLeft(int $orderId, OrderItem $line): int
    {
        $used = DB::table('service_request_items as i')
            ->join('service_requests as r', 'r.id', '=', 'i.service_request_id')
            ->where('r.order_id', $orderId)
            ->where('i.order_item_id', $line->id)
            ->where(function ($w) {
                // a finished warranty claim frees the pieces (the unit can fail again inside the warranty);
                // an exchange / wrong-product claim, finished or not, has used them
                $w->where(function ($x) {
                    $x->where('r.type', '!=', 'warranty')->whereIn('r.status', ServiceRequest::activeStatuses());
                })->orWhere(function ($x) {
                    $x->where('r.type', 'warranty')->whereIn('r.status', ['open', 'approved']);
                });
            })
            ->sum('i.quantity');

        return max(0, (int) $line->quantity - (int) $used);
    }

    // ------------------------------------------------------------------ open

    /**
     * @param array $d order_item_id, quantity, reason, symptoms?, serial_number?, start_date?, record_ineligible?
     */
    public function open(Order $order, array $d): ServiceRequest
    {
        $this->assertReady();

        $reason = trim((string) ($d['reason'] ?? ''));
        if ($reason === '') {
            throw new \RuntimeException('Please write why the customer is claiming the warranty.');
        }
        $qty = (int) ($d['quantity'] ?? 0);
        if ($qty < 1) {
            throw new \RuntimeException('Please enter how many pieces are part of the claim.');
        }
        $record = !empty($d['record_ineligible']);

        $claim = DB::transaction(function () use ($order, $d, $reason, $qty, $record) {
            // Lock the order so two people cannot claim the same piece at the same moment
            $o = Order::with('items')->lockForUpdate()->findOrFail($order->id);

            if ($o->status !== 'delivered') {
                throw new \RuntimeException('A warranty claim can only be opened for a delivered order (order #' . $o->id . ' is ' . strtolower($o->statusLabel()) . ').');
            }

            $line = $o->items->firstWhere('id', (int) ($d['order_item_id'] ?? 0));
            if (!$line) {
                throw new \RuntimeException('Please choose the product of this order that has the problem.');
            }

            $left = $this->piecesLeft($o->id, $line);
            if ($qty > $left) {
                throw new \RuntimeException('"' . $line->title . '": only ' . $left . ' piece(s) can still be claimed (ordered ' . (int) $line->quantity . '; the others are in another open claim or an exchange).');
            }

            $a = $this->assess($o, $line, $d['start_date'] ?? null);

            if ($a['state'] === 'unknown') {
                throw new \RuntimeException($a['message']);
            }
            $eligible = $a['state'] === 'in_warranty';
            if (!$eligible && !$record) {
                throw new \RuntimeException($a['message'] . ' If you still want a record of this request, tick "Save it anyway as rejected".');
            }

            // Old line without a snapshot: keep the policy we just used, so it cannot change later
            if (empty($line->warranty_source)) {
                $line->update([
                    'warranty_type'   => $a['policy']['type'],
                    'warranty_days'   => $a['policy']['days'] > 0 ? $a['policy']['days'] : null,
                    'warranty_terms'  => $a['policy']['terms'],
                    'warranty_source' => 'backfilled',
                ]);
            }

            $staff = AdminAccess::staff();
            $claim = ServiceRequest::create([
                'order_id'              => $o->id,
                'customer_name'         => $o->name,
                'customer_phone'        => $o->phone,
                'type'                  => 'warranty',
                'status'                => $eligible ? 'open' : 'rejected',
                'reason'                => $reason,
                'symptoms'              => $this->clean($d['symptoms'] ?? null, 2000),
                'serial_number'         => $this->clean($d['serial_number'] ?? null, 100),
                'return_required'       => true,
                'opened_by_id'          => $staff ? $staff->id : null,
                'opened_by_name'        => !empty($d['opened_by_name']) ? (string) $d['opened_by_name'] : AdminAccess::name(),
                'warranty_type'         => $a['policy']['type'],
                'warranty_days'         => $a['policy']['days'] > 0 ? $a['policy']['days'] : null,
                'warranty_terms'        => $a['policy']['terms'],
                'warranty_source'       => $a['policy']['source'],
                'warranty_start_at'     => $a['start'],
                'warranty_start_source' => $a['start_source'],
                'warranty_expires_at'   => $a['expires'],
                'warranty_state'        => $a['state'],
            ] + ($eligible ? [] : [
                'decision_note'   => 'Not accepted automatically: ' . $a['message'],
                'decided_by_name' => 'System (automatic)',
                'decided_at'      => now(),
            ]));

            $claim->items()->create([
                'order_item_id' => $line->id,
                'product_id'    => $line->product_id,
                'title'         => $line->title,
                'price'         => (int) $line->price,
                'quantity'      => $qty,
            ]);

            return $claim;
        });

        $this->log($claim, $claim->status === 'open' ? 'warranty.opened' : 'warranty.opened_rejected',
            'Warranty claim #' . $claim->id . ' for order #' . $claim->order_id . ($claim->status === 'open' ? ' opened' : ' recorded as rejected (' . $claim->warranty_state . ')'),
            null,
            ['order_id' => $claim->order_id, 'state' => $claim->warranty_state, 'policy' => $claim->warranty_type, 'days' => $claim->warranty_days, 'expires' => optional($claim->warranty_expires_at)->toDateString()],
            !empty($d['opened_by_name']) ? (string) $d['opened_by_name'] : null
        );

        return $claim;
    }

    // ------------------------------------------------------------------ decisions

    /** The claim is accepted for inspection: the product may come in. The solution is decided AFTER the inspection. */
    public function accept(ServiceRequest $claim, ?string $note): ServiceRequest
    {
        $this->assertWarranty($claim);

        DB::transaction(function () use (&$claim, $note) {
            $claim = ServiceRequest::lockForUpdate()->findOrFail($claim->id);
            if ($claim->status !== 'open') {
                throw new \RuntimeException('Claim #' . $claim->id . ' is already ' . strtolower($claim->statusLabel()) . '.');
            }
            if ($claim->warranty_state !== 'in_warranty') {
                throw new \RuntimeException('This claim is outside the warranty and cannot be accepted.');
            }

            $staff = AdminAccess::staff();
            $claim->update([
                'status'          => 'approved',
                'decision_note'   => $this->clean($note, 1000),
                'decided_by_id'   => $staff ? $staff->id : null,
                'decided_by_name' => AdminAccess::name(),
                'decided_at'      => now(),
            ]);
        });

        $this->log($claim, 'warranty.accepted', 'Warranty claim #' . $claim->id . ' accepted for inspection', ['status' => 'open'], ['status' => 'approved']);

        return $claim;
    }

    /** Reject with a reason: before the inspection, or after it when the fault is not covered. */
    public function reject(ServiceRequest $claim, ?string $note): ServiceRequest
    {
        $this->assertWarranty($claim);
        if (trim((string) $note) === '') {
            throw new \RuntimeException('Please write why the claim is rejected.');
        }

        $old = [];
        DB::transaction(function () use (&$claim, $note, &$old) {
            $claim = ServiceRequest::lockForUpdate()->findOrFail($claim->id);
            if (!in_array($claim->status, ['open', 'approved'], true)) {
                throw new \RuntimeException('Claim #' . $claim->id . ' is already ' . strtolower($claim->statusLabel()) . '.');
            }
            if ($claim->resolution || $claim->replacement_order_id) {
                throw new \RuntimeException('A solution (' . $claim->resolutionLabel() . ') was already chosen, so this claim cannot be rejected any more.');
            }

            $old   = ['status' => $claim->status, 'note' => $claim->decision_note];
            $staff = AdminAccess::staff();
            $claim->update([
                'status'          => 'rejected',
                'decision_note'   => $this->clean($note, 1000),
                'decided_by_id'   => $staff ? $staff->id : null,
                'decided_by_name' => AdminAccess::name(),
                'decided_at'      => now(),
            ]);
        });

        $this->log($claim, 'warranty.rejected', 'Warranty claim #' . $claim->id . ' rejected', $old, ['status' => 'rejected', 'note' => $claim->decision_note]);

        return $claim;
    }

    // ------------------------------------------------------------------ inspection

    /** Records what was found on the returned unit. It cannot be changed afterwards. */
    public function inspect(ServiceRequest $claim, array $d): ServiceRequest
    {
        $this->assertWarranty($claim);

        $result    = (string) ($d['inspection_result'] ?? '');
        $condition = trim((string) ($d['received_condition'] ?? ''));
        if (!array_key_exists($result, ServiceRequest::inspectionResults())) {
            throw new \RuntimeException('Please choose the inspection result.');
        }
        if ($condition === '') {
            throw new \RuntimeException('Please describe the condition of the product you received.');
        }

        DB::transaction(function () use (&$claim, $result, $condition, $d) {
            $claim = ServiceRequest::with('shipments')->lockForUpdate()->findOrFail($claim->id);

            if ($claim->status !== 'approved') {
                throw new \RuntimeException('Only an accepted claim can be inspected.');
            }
            if ($claim->inspected_at) {
                throw new \RuntimeException('This claim was already inspected and the result cannot be changed.');
            }
            if (!$this->hasReceivedShipment($claim)) {
                throw new \RuntimeException('Record the returned product first: add a shipment "Coming back from the customer" with the status "Received" (for a hand delivery write "Hand delivery" as the courier).');
            }

            $claim->update([
                'received_condition' => Str::limit($condition, 1000, ''),
                'inspection_result'  => $result,
                'inspection_note'    => $this->clean($d['inspection_note'] ?? null, 1000),
                'inspected_at'       => now(),
                'inspected_by_name'  => AdminAccess::name(),
            ]);
        });

        $this->log($claim, 'warranty.inspected', 'Warranty claim #' . $claim->id . ' inspected: ' . (ServiceRequest::inspectionResults()[$result] ?? $result), null,
            ['result' => $result, 'condition' => $condition, 'note' => $claim->inspection_note]);

        return $claim;
    }

    // ------------------------------------------------------------------ resolution

    /** Repair or replacement. Only a defect that was confirmed, and only what the policy of this purchase allows. */
    public function resolve(ServiceRequest $claim, string $resolution, ?string $note): ServiceRequest
    {
        $this->assertWarranty($claim);

        DB::transaction(function () use (&$claim, $resolution, $note) {
            $claim = ServiceRequest::lockForUpdate()->findOrFail($claim->id);

            if ($claim->status !== 'approved') {
                throw new \RuntimeException('Only an accepted claim can get a solution.');
            }
            if (!$claim->inspected_at) {
                throw new \RuntimeException('Inspect the returned product first.');
            }
            if ($claim->inspection_result !== 'defect_confirmed') {
                throw new \RuntimeException('The inspection did not confirm a covered defect, so no free repair or replacement can be given. Reject the claim with the reason.');
            }
            if ($claim->resolution) {
                throw new \RuntimeException('A solution was already chosen (' . $claim->resolutionLabel() . ').');
            }

            $allowed = WarrantyPolicy::allowedResolutions($claim->warranty_type);
            if (!in_array($resolution, $allowed, true)) {
                $names = array_map(fn ($k) => ServiceRequest::warrantyResolutions()[$k] ?? $k, $allowed);
                throw new \RuntimeException('The warranty of this purchase (' . WarrantyPolicy::typeLabel($claim->warranty_type) . ') does not allow that. It allows: ' . ($names ? implode(', ', $names) : 'nothing') . '.');
            }

            $claim->update([
                'resolution'    => $resolution,
                'decision_note' => trim(($claim->decision_note ? $claim->decision_note . "\n" : '') . ($this->clean($note, 500) ? 'Solution: ' . $this->clean($note, 500) : '')) ?: $claim->decision_note,
            ]);
        });

        $this->log($claim, 'warranty.resolved_as', 'Warranty claim #' . $claim->id . ' will be solved by ' . $resolution, null, ['resolution' => $resolution]);

        return $claim;
    }

    /** Serial number of the customer's unit and of the replacement we send */
    public function saveSerials(ServiceRequest $claim, ?string $serial, ?string $replacementSerial): ServiceRequest
    {
        $this->assertWarranty($claim);
        if (!in_array($claim->status, ['open', 'approved'], true)) {
            throw new \RuntimeException('This claim is closed, so the serial numbers cannot be changed.');
        }

        $serial      = $this->clean($serial, 100);
        $replacement = $this->clean($replacementSerial, 100);
        if ($replacement !== null && $claim->resolution !== 'replacement') {
            throw new \RuntimeException('The replacement serial number can be saved once the solution "Replacement" is chosen.');
        }

        $old = $claim->only(['serial_number', 'replacement_serial']);
        $claim->update(['serial_number' => $serial, 'replacement_serial' => $replacement]);

        $this->log($claim, 'warranty.serials', 'Serial numbers of warranty claim #' . $claim->id . ' saved', $old, ['serial_number' => $serial, 'replacement_serial' => $replacement]);

        return $claim;
    }

    // ------------------------------------------------------------------ close

    /** Why the claim cannot be finished yet (null = it can) */
    public function closeProblem(ServiceRequest $claim): ?string
    {
        $claim->loadMissing('shipments');

        if ($claim->status !== 'approved') {
            return 'Only an accepted claim can be finished.';
        }
        if (!$claim->inspected_at) {
            return 'Inspect the returned product first.';
        }
        if ($claim->inspection_result !== 'defect_confirmed') {
            return 'The defect was not confirmed: reject the claim with the reason instead of finishing it.';
        }
        if (!$claim->resolution) {
            return 'Choose the solution (repair or replacement) first.';
        }
        if ($claim->resolution === 'replacement' && !$claim->replacement_order_id) {
            return 'Make the replacement order first.';
        }
        if ($claim->resolution === 'repair' && !$claim->shipments->contains(fn ($s) => $s->direction === 'outbound' && in_array($s->status, ['in_transit', 'received'], true))) {
            return 'Add the shipment that sends the repaired product back to the customer ("Going to the customer", status "On the way" or "Received").';
        }

        return null;
    }

    // ------------------------------------------------------------------ screen helpers

    /** What the claim screen needs to show */
    public function overview(ServiceRequest $claim): array
    {
        $claim->loadMissing('shipments');

        $received   = $this->hasReceivedShipment($claim);
        $dispatched = $claim->resolution === 'replacement'
            ? (bool) $claim->replacement_order_id
            : $claim->shipments->contains(fn ($s) => $s->direction === 'outbound' && in_array($s->status, ['in_transit', 'received'], true));

        $steps = [
            ['Submitted', true],
            ['Accepted for inspection', in_array($claim->status, ['approved', 'resolved'], true)],
            ['Product received', $received],
            ['Inspected', (bool) $claim->inspected_at],
            ['Solution chosen', (bool) $claim->resolution],
            [$claim->resolution === 'replacement' ? 'Replacement ordered' : 'Sent back', $dispatched],
            ['Completed', $claim->status === 'resolved'],
        ];

        return [
            'allowed'    => WarrantyPolicy::allowedResolutions($claim->warranty_type),
            'received'   => $received,
            'dispatched' => $dispatched,
            'steps'      => $steps,
            'rejected'   => $claim->status === 'rejected',
        ];
    }

    // ------------------------------------------------------------------ helpers

    private function hasReceivedShipment(ServiceRequest $claim): bool
    {
        return $claim->shipments->contains(fn ($s) => $s->direction === 'inbound' && $s->status === 'received');
    }

    private function assertReady(): void
    {
        if (!ServiceRequest::tableReady() || !WarrantyPolicy::columnsReady()) {
            throw new \RuntimeException('The warranty columns are missing. Run "php artisan migrate" first.');
        }
    }

    private function assertWarranty(ServiceRequest $claim): void
    {
        if (!$claim->isWarranty()) {
            throw new \RuntimeException('Claim #' . $claim->id . ' is not a warranty claim.');
        }
    }

    private function clean(?string $v, int $max): ?string
    {
        $v = trim((string) $v);

        return $v === '' ? null : Str::limit($v, $max, '');
    }

    private function log(ServiceRequest $claim, string $action, string $summary, ?array $old = null, ?array $new = null, ?string $actor = null): void
    {
        // subject_type "claim" like the exchange claims, so the History box of the claim page shows these lines too
        Audit::log($action, 'claim', $claim->id, $summary, $old, $new, $claim->customer_phone, $actor);
    }
}
