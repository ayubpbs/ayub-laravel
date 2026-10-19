<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ServiceRequest;
use App\Services\ClaimService;
use App\Services\WarrantyService;
use App\Support\WarrantyPolicy;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin: warranty claims. What each action needs is decided by route name in App\Support\AdminAccess:
 * opening = claims_manage, accept / reject / solution = aftersales_approve, inspection = claims_inspect.
 * Evidence, shipments, replacement orders and finishing reuse the claim screens of phase 5.
 */
class WarrantyController extends Controller
{
    private WarrantyService $warranty;
    private ClaimService $claims;

    public function __construct(WarrantyService $warranty, ClaimService $claims)
    {
        $this->warranty = $warranty;
        $this->claims   = $claims;
    }

    // ------------------------------------------------------------------ open

    public function create(Request $request)
    {
        if (!ServiceRequest::tableReady() || !WarrantyPolicy::columnsReady()) {
            return redirect()->route('admin.claims.index')->with('error', 'The warranty columns are missing. Run "php artisan migrate" first.');
        }

        $order  = null;
        $rows   = [];
        $lookup = trim((string) $request->query('order', ''));

        if ($lookup !== '') {
            $order = Order::with('items')->find((int) ltrim($lookup, '#'));
            if (!$order) {
                return redirect()->route('admin.claims.warranty.create')->with('error', 'Order "' . $lookup . '" was not found.');
            }

            foreach ($order->items as $line) {
                // The date is only needed when the order has no recorded delivery date; the preview then says "date needed"
                $rows[] = ['line' => $line, 'left' => $this->warranty->piecesLeft($order->id, $line), 'a' => $this->warranty->assess($order, $line, null)];
            }
        }

        return view('admin.claims.warranty_create', ['order' => $order, 'rows' => $rows]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'order_id'          => 'required|integer|exists:orders,id',
            'order_item_id'     => 'required|integer',
            'quantity'          => 'required|integer|min:1|max:999',
            'reason'            => 'required|string|max:1000',
            'symptoms'          => 'nullable|string|max:2000',
            'serial_number'     => 'nullable|string|max:100',
            'start_date'        => 'nullable|date',
            'record_ineligible' => 'nullable|boolean',
            'files'             => 'nullable|array',
            'files.*'           => 'nullable|file',
            'evidence_note'     => 'nullable|string|max:250',
        ], [
            'order_item_id.required' => 'Please choose the product that has the problem.',
            'reason.required'        => 'Please write why the customer is claiming the warranty.',
        ]);

        $files  = (array) $request->file('files', []);
        $errors = $this->claims->checkFiles($files);
        if ($errors) {
            return back()->withInput()->with('error', implode(' ', $errors));
        }

        try {
            $claim = $this->warranty->open(Order::findOrFail($data['order_id']), $data + ['record_ineligible' => $request->boolean('record_ineligible')]);
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        $saved = $files ? $this->claims->addEvidence($claim, $files, $data['evidence_note'] ?? null) : 0;

        $msg = $claim->status === 'open'
            ? 'Warranty claim #' . $claim->id . ' opened. ' . ($claim->warranty_expires_at ? 'Warranty valid until ' . $claim->warranty_expires_at->format('d M Y') . '.' : '')
            : 'Warranty claim #' . $claim->id . ' saved as REJECTED: ' . $claim->decision_note;

        return redirect()->route('admin.claims.show', $claim->id)->with($claim->status === 'open' ? 'success' : 'error', trim($msg . ($saved ? ' ' . $saved . ' file(s) saved privately.' : '')));
    }

    // ------------------------------------------------------------------ decisions and steps

    public function accept(Request $request, $id)
    {
        $d = $request->validate(['note' => 'nullable|string|max:1000']);

        return $this->run(fn () => $this->warranty->accept($this->claim($id), $d['note'] ?? null), $id, 'Claim accepted. The customer can now send the product for inspection.');
    }

    public function reject(Request $request, $id)
    {
        $d = $request->validate(['note' => 'required|string|max:1000'], ['note.required' => 'Please write why the claim is rejected.']);

        return $this->run(fn () => $this->warranty->reject($this->claim($id), $d['note']), $id, 'Claim rejected.');
    }

    public function inspect(Request $request, $id)
    {
        $d = $request->validate([
            'inspection_result'  => ['required', Rule::in(array_keys(ServiceRequest::inspectionResults()))],
            'received_condition' => 'required|string|max:1000',
            'inspection_note'    => 'nullable|string|max:1000',
        ], ['inspection_result.required' => 'Please choose the inspection result.', 'received_condition.required' => 'Please describe the condition of the product you received.']);

        return $this->run(fn () => $this->warranty->inspect($this->claim($id), $d), $id, 'Inspection saved.');
    }

    public function resolve(Request $request, $id)
    {
        $d = $request->validate([
            'resolution' => ['required', Rule::in(array_keys(ServiceRequest::warrantyResolutions()))],
            'note'       => 'nullable|string|max:500',
        ], ['resolution.required' => 'Please choose repair or replacement.']);

        return $this->run(fn () => $this->warranty->resolve($this->claim($id), $d['resolution'], $d['note'] ?? null), $id, 'Solution saved.');
    }

    public function serials(Request $request, $id)
    {
        $d = $request->validate(['serial_number' => 'nullable|string|max:100', 'replacement_serial' => 'nullable|string|max:100']);

        return $this->run(fn () => $this->warranty->saveSerials($this->claim($id), $d['serial_number'] ?? null, $d['replacement_serial'] ?? null), $id, 'Serial numbers saved.');
    }

    // ------------------------------------------------------------------ helpers

    private function claim($id): ServiceRequest
    {
        return ServiceRequest::where('type', 'warranty')->findOrFail($id);
    }

    private function run(callable $action, $id, string $success)
    {
        try {
            $action();
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', $success);
    }
}
