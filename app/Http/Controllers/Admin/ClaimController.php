<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestFile;
use App\Models\ServiceRequestShipment;
use App\Services\ClaimService;
use App\Services\WarrantyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Admin: exchange / wrong-product / defective claims, replacement orders, return shipments, private evidence.
 * What each action needs is decided by route name in App\Support\AdminAccess (claims_view, claims_manage,
 * claims_inspect; refunds also need "payments", replacement orders also need "orders_create").
 */
class ClaimController extends Controller
{
    private ClaimService $claims;

    public function __construct(ClaimService $claims)
    {
        $this->claims = $claims;
    }

    // ------------------------------------------------------------------ list

    private function dateOrEmpty($value): string
    {
        $value = trim((string) $value);

        return $value !== '' && strtotime($value) !== false ? date('Y-m-d', strtotime($value)) : '';
    }

    public function index(Request $request)
    {
        $ready  = ServiceRequest::tableReady();
        $status = (string) $request->query('status', '');
        $q      = trim((string) $request->query('q', ''));
        $kind   = in_array($request->query('kind'), ['warranty', 'exchange'], true) ? (string) $request->query('kind') : '';
        $type   = array_key_exists((string) $request->query('type'), ServiceRequest::allTypes()) ? (string) $request->query('type') : '';
        $from   = $this->dateOrEmpty($request->query('from'));
        $to     = $this->dateOrEmpty($request->query('to'));
        $product = trim((string) $request->query('product', ''));

        if (!$ready) {
            return view('admin.claims.index', ['ready' => false, 'claims' => null, 'counts' => collect(), 'status' => '', 'q' => '', 'kind' => '', 'type' => '', 'from' => '', 'to' => '', 'product' => '']);
        }

        $query = ServiceRequest::with('order')->latest('id');
        if (array_key_exists($status, ServiceRequest::statuses())) {
            $query->where('status', $status);
        } else {
            $status = '';
        }
        if ($kind === 'warranty') {
            $query->where('type', 'warranty');
        } elseif ($kind === 'exchange') {
            $query->where('type', '!=', 'warranty');
        }
        if ($type !== '') {
            $query->where('type', $type);
        }
        if ($from !== '') {
            $query->whereDate('created_at', '>=', $from);
        }
        if ($to !== '') {
            $query->whereDate('created_at', '<=', $to);
        }
        if ($product !== '') {
            $query->whereHas('items', fn ($i) => $i->where('title', 'like', "%{$product}%"));
        }
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('customer_name', 'like', "%{$q}%")->orWhere('customer_phone', 'like', "%{$q}%")
                  ->orWhere('id', ltrim($q, '#'))->orWhere('order_id', ltrim($q, '#'));
            });
        }

        return view('admin.claims.index', [
            'ready'  => true,
            'claims' => $query->paginate(20)->withQueryString(),
            'counts' => ServiceRequest::select('status', DB::raw('count(*) as c'))
                ->when($kind === 'warranty', fn ($w) => $w->where('type', 'warranty'))
                ->when($kind === 'exchange', fn ($w) => $w->where('type', '!=', 'warranty'))
                ->groupBy('status')->pluck('c', 'status'),
            'status' => $status,
            'q'      => $q,
            'kind'   => $kind,
            'type'   => $type,
            'from'   => $from,
            'to'     => $to,
            'product' => $product,
        ]);
    }

    // ------------------------------------------------------------------ open a claim

    public function create(Request $request)
    {
        if (!ServiceRequest::tableReady()) {
            return redirect()->route('admin.claims.index');
        }

        $order  = null;
        $lookup = trim((string) $request->query('order', ''));
        $left   = [];

        if ($lookup !== '') {
            $order = Order::with('items')->find((int) ltrim($lookup, '#'));
            if (!$order) {
                return redirect()->route('admin.claims.create')->with('error', 'Order "' . $lookup . '" was not found.');
            }

            // How many pieces of each line can still be claimed
            $used = DB::table('service_request_items as i')
                ->join('service_requests as r', 'r.id', '=', 'i.service_request_id')
                ->where('r.order_id', $order->id)
                ->where(function ($w) {
                    $w->where(function ($x) {
                        $x->where('r.type', '!=', 'warranty')->whereIn('r.status', ServiceRequest::activeStatuses());
                    })->orWhere(function ($x) {
                        $x->where('r.type', 'warranty')->whereIn('r.status', ['open', 'approved']);
                    });
                })
                ->selectRaw('i.order_item_id as oi, SUM(i.quantity) as q')->groupBy('i.order_item_id')->pluck('q', 'oi');
            foreach ($order->items as $it) {
                $left[$it->id] = max(0, (int) $it->quantity - (int) ($used[$it->id] ?? 0));
            }
        }

        return view('admin.claims.create', ['order' => $order, 'left' => $left]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'order_id'        => 'required|integer|exists:orders,id',
            'type'            => ['required', Rule::in(array_keys(ServiceRequest::types()))],
            'reason'          => 'nullable|string|max:2000',
            'return_required' => 'nullable|boolean',
            'qty'             => 'required|array',
            'qty.*'           => 'nullable|integer|min:0|max:999',
            'received'        => 'nullable|array',
            'received.*'      => 'nullable|integer|min:1',
            'files'           => 'nullable|array',
            'files.*'         => 'nullable|file',
            'evidence_note'   => 'nullable|string|max:250',
        ], [
            'type.required' => 'Please choose the type of claim.',
            'qty.required'  => 'Please enter the quantity for at least one product.',
        ]);

        $files  = (array) $request->file('files', []);
        $errors = $this->claims->checkFiles($files);
        if ($errors) {
            return back()->withInput()->with('error', implode(' ', $errors));
        }

        $order = Order::findOrFail($data['order_id']);

        try {
            $claim = $this->claims->open(
                $order,
                ['type' => $data['type'], 'reason' => $data['reason'] ?? null, 'return_required' => $request->boolean('return_required')],
                (array) ($data['qty'] ?? []),
                (array) ($data['received'] ?? [])
            );
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $saved = $files ? $this->claims->addEvidence($claim, $files, $data['evidence_note'] ?? null) : 0;

        return redirect()->route('admin.claims.show', $claim->id)
            ->with('success', 'Claim #' . $claim->id . ' opened.' . ($saved ? ' ' . $saved . ' file(s) saved privately.' : ''));
    }

    // ------------------------------------------------------------------ one claim

    public function show($id)
    {
        $claim = ServiceRequest::with(['items', 'files', 'shipments', 'order.items', 'replacementOrder'])->findOrFail($id);

        $logs = AuditLog::tableReady()
            ? AuditLog::where('subject_type', 'claim')->where('subject_id', $claim->id)->latest('id')->limit(40)->get()
            : collect();

        $order = $claim->order;

        return view('admin.claims.show', [
            'claim'        => $claim,
            'order'        => $order,
            'logs'         => $logs,
            'held'         => $order ? $order->moneyHeld() : 0,
            'refunded'     => $claim->refundedAmount(),
            'excess'       => $this->claims->exchangeExcess($claim),
            'closeProblem' => $this->claims->closeProblem($claim),
            'warranty'     => $claim->isWarranty() ? app(WarrantyService::class)->overview($claim) : null,
            'token'        => Str::random(40),
        ]);
    }

    public function decide(Request $request, $id)
    {
        $claim = ServiceRequest::findOrFail($id);
        $d     = $request->validate([
            'action'     => 'required|in:approve,reject',
            'resolution' => 'nullable|string|max:20',
            'note'       => 'nullable|string|max:1000',
        ]);

        try {
            if ($d['action'] === 'approve') {
                $this->claims->approve($claim, (string) ($d['resolution'] ?? ''), $d['note'] ?? null);
                $msg = 'Claim #' . $claim->id . ' approved.';
            } else {
                $this->claims->reject($claim, $d['note'] ?? null);
                $msg = 'Claim #' . $claim->id . ' rejected.';
            }
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', $msg);
    }

    // ------------------------------------------------------------------ replacement / exchange order

    public function order(Request $request, $id)
    {
        $claim = ServiceRequest::findOrFail($id);
        $d     = $request->validate([
            'client_token'       => 'required|string|max:64',
            'items'              => 'required|array|min:1|max:30',
            'items.*.product_id' => 'required|integer|min:1',
            'items.*.quantity'   => 'required|integer|min:1|max:999',
            'credit'             => 'nullable|integer|min:0|max:10000000',
            'delivery'           => 'nullable|integer|min:0|max:100000',
        ], ['items.required' => 'Please add at least one product to the new order.']);

        $lines = [];
        foreach ($d['items'] as $row) {
            $lines[(int) $row['product_id']] = ($lines[(int) $row['product_id']] ?? 0) + (int) $row['quantity'];
        }

        try {
            [$order, $created] = $this->claims->createOrder(
                $claim, $lines, isset($d['credit']) ? (int) $d['credit'] : null, (int) ($d['delivery'] ?? 0), $d['client_token']
            );
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        if (!$created) {
            return redirect()->route('admin.claims.show', $claim->id)->with('success', 'This order was already created (the form was sent twice), so no second order was made.');
        }

        return redirect()->route('admin.orders.show', $order->id)
            ->with('success', ucfirst($order->order_type) . ' order #' . $order->id . ' created from claim #' . $claim->id . '. Confirm it and send it with the normal "Send to Steadfast" button.');
    }

    // ------------------------------------------------------------------ refund

    public function refund(Request $request, $id)
    {
        $claim = ServiceRequest::findOrFail($id);
        $d     = $request->validate([
            'amount'  => 'required|integer|min:1|max:10000000',
            'method'  => ['required', Rule::in(array_keys(Payment::methods()))],
            'trx_id'  => 'nullable|string|max:100',
            'note'    => 'nullable|string|max:200',
        ], ['amount.required' => 'Please enter the refund amount.', 'amount.integer' => 'The amount must be a whole number of taka.']);

        try {
            $p = $this->claims->refund($claim, (int) $d['amount'], $d['method'], $d['trx_id'] ?? null, $d['note'] ?? null);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Refund of ৳ ' . number_format($p->amount) . ' recorded for claim #' . $claim->id . '.');
    }

    // ------------------------------------------------------------------ inspection / close

    public function inspect(Request $request, $id)
    {
        $claim = ServiceRequest::findOrFail($id);
        $d     = $request->validate([
            'rows'            => 'required|array',
            'rows.*.restock'  => 'nullable|integer|min:0|max:999',
            'rows.*.supplier' => 'nullable|integer|min:0|max:999',
            'rows.*.scrap'    => 'nullable|integer|min:0|max:999',
            'rows.*.note'     => 'nullable|string|max:250',
        ]);

        try {
            $n = $this->claims->inspect($claim, (array) $d['rows']);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', $n . ' product line(s) inspected. Only the pieces marked "back to stock" were added to the stock.');
    }

    public function close($id)
    {
        $claim = ServiceRequest::findOrFail($id);

        try {
            $this->claims->close($claim);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Claim #' . $claim->id . ' is resolved.');
    }

    // ------------------------------------------------------------------ evidence

    public function evidenceStore(Request $request, $id)
    {
        $claim = ServiceRequest::findOrFail($id);
        $request->validate(['files' => 'required|array|min:1', 'files.*' => 'file', 'note' => 'nullable|string|max:250'], ['files.required' => 'Please choose at least one photo or video.']);

        $files  = (array) $request->file('files', []);
        $errors = $this->claims->checkFiles($files, $claim->files()->count());
        if ($errors) {
            return back()->with('error', implode(' ', $errors));
        }

        $saved = $this->claims->addEvidence($claim, $files, $request->input('note'));

        return back()->with($saved ? 'success' : 'error', $saved ? $saved . ' file(s) saved privately.' : 'No file could be saved.');
    }

    /** Streams one private file to a signed-in admin who may view claims. Nothing here is reachable by URL guessing. */
    public function evidenceShow($id, $fileId)
    {
        $file = ServiceRequestFile::where('service_request_id', $id)->findOrFail($fileId);
        $path = Storage::disk('local')->path($file->path);
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type'            => $file->mime,
            'Content-Disposition'     => 'inline; filename="claim-' . (int) $id . '-file-' . $file->id . '.' . pathinfo($file->path, PATHINFO_EXTENSION) . '"',
            'X-Content-Type-Options'  => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control'           => 'private, no-store',
        ]);
    }

    public function evidenceDestroy($id, $fileId)
    {
        $file = ServiceRequestFile::where('service_request_id', $id)->findOrFail($fileId);
        $this->claims->deleteEvidence($file);

        return back()->with('success', 'File removed.');
    }

    // ------------------------------------------------------------------ shipments

    public function shipmentStore(Request $request, $id)
    {
        $claim = ServiceRequest::findOrFail($id);
        $d     = $request->validate([
            'direction'     => ['required', Rule::in(array_keys(ServiceRequestShipment::directions()))],
            'courier'       => 'nullable|string|max:60',
            'tracking_code' => 'nullable|string|max:100',
            'status'        => ['required', Rule::in(array_keys(ServiceRequestShipment::statuses()))],
            'shipped_at'    => 'nullable|date',
            'note'          => 'nullable|string|max:250',
        ]);

        $this->claims->addShipment($claim, $d);

        return back()->with('success', 'Shipment added.');
    }

    public function shipmentUpdate(Request $request, $id, $shipmentId)
    {
        $ship = ServiceRequestShipment::where('service_request_id', $id)->findOrFail($shipmentId);
        $d    = $request->validate([
            'status' => ['required', Rule::in(array_keys(ServiceRequestShipment::statuses()))],
            'note'   => 'nullable|string|max:250',
        ]);

        $this->claims->updateShipment($ship, $d['status'], $d['note'] ?? null);

        return back()->with('success', 'Shipment updated.');
    }

    // ------------------------------------------------------------------ product search (for the exchange form)

    public function products(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';

        return response()->json(
            Product::where('is_upcoming', 0)
                ->where(function ($w) use ($like) {
                    $w->where('title', 'like', $like)->orWhere('sku', 'like', $like);
                })
                ->orderByDesc('stock_quantity')->limit(15)->get()
                ->map(fn ($p) => ['id' => $p->id, 'title' => $p->title, 'sku' => $p->sku, 'price' => (int) $p->price, 'stock' => (int) $p->stock_quantity])
                ->values()
        );
    }
}
