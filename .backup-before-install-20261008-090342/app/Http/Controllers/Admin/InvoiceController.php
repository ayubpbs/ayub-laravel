<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $q    = trim((string) $request->query('q', ''));
        $pay  = (string) $request->query('pay', '');
        $from = (string) $request->query('from', '');
        $to   = (string) $request->query('to', '');

        $query = Invoice::with(['order' => fn ($o) => $o->withPaid()])->orderByDesc('id');

        if ($q !== '') {
            $digits = (int) preg_replace('/\D/', '', $q);
            $query->where(function ($w) use ($q, $digits) {
                if ($digits > 0) {
                    $w->where('id', $digits)->orWhere('order_id', $digits);
                }
                $w->orWhereHas('order', fn ($o) => $o->where('name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%"));
            });
        }
        if ($pay === 'paid') {
            $query->whereHas('order', fn ($o) => $o->settled());
        } elseif ($pay === 'partial') {
            $query->whereHas('order', fn ($o) => $o->partlyPaid());
        } elseif ($pay === 'unpaid') {
            $query->whereHas('order', fn ($o) => $o->nothingPaid());
        } else {
            $pay = '';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $query->whereDate('issued_at', '>=', $from);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $query->whereDate('issued_at', '<=', $to);
        }

        $invoices = $query->paginate(25)->withQueryString();

        $stats = [
            'total'   => Invoice::count(),
            'month'   => Invoice::where('issued_at', '>=', now()->startOfMonth())->count(),
            'unpaid'  => Invoice::whereHas('order', fn ($o) => $o->whereNotIn('status', Order::lockedStatuses())->owing())->count(),
            'missing' => $this->missingOrders()->count(),
        ];

        return view('admin.invoices.index', compact('invoices', 'stats', 'q', 'pay', 'from', 'to'));
    }

    /** One invoice, ready to print */
    public function show($id)
    {
        $invoice = Invoice::with(['order.items', 'order.payments'])->findOrFail($id);

        return view('admin.invoices.print', [
            'invoices' => collect([$invoice]),
            'public'   => false,
            'link'     => URL::signedRoute('invoice.public', ['id' => $invoice->id]),
        ]);
    }

    /** Several invoices on one printout: /admin/invoices/print?ids=1,2,3 */
    public function printMany(Request $request)
    {
        $ids = collect(explode(',', (string) $request->query('ids', '')))
            ->map(fn ($v) => (int) $v)->filter()->unique()->take(50)->values();

        if ($ids->isEmpty()) {
            return redirect()->route('admin.invoices.index')->with('error', 'Please tick at least one invoice to print.');
        }

        $invoices = Invoice::with(['order.items', 'order.payments'])->whereIn('id', $ids)->orderBy('id')->get();

        return view('admin.invoices.print', ['invoices' => $invoices, 'public' => false, 'link' => null]);
    }

    /** The customer's own copy, reachable only through the signed link made in show() */
    public function publicShow($id)
    {
        $invoice = Invoice::with(['order.items', 'order.payments'])->findOrFail($id);

        return view('admin.invoices.print', ['invoices' => collect([$invoice]), 'public' => true, 'link' => null]);
    }

    /** Creates the invoice for one order (or opens it if it already exists) */
    public function generate($orderId)
    {
        $order = Order::findOrFail($orderId);

        if ($order->isLocked()) {
            return back()->with('error', 'Order #' . $order->id . ' is ' . strtolower($order->statusLabel()) . ', so no invoice can be made for it.');
        }

        $invoice = Invoice::ensureFor($order);

        return redirect()->route('admin.invoices.show', $invoice->id);
    }

    /** Creates invoices for every confirmed / sent / delivered order that does not have one */
    public function generateMissing()
    {
        $count = 0;
        foreach ($this->missingOrders()->orderBy('id')->get() as $order) {
            Invoice::ensureFor($order);
            $count++;
        }

        return back()->with('success', $count ? "{$count} invoice(s) created." : 'Every eligible order already has an invoice.');
    }

    private function missingOrders()
    {
        return Order::whereIn('status', Invoice::eligibleStatuses())->whereDoesntHave('invoice');
    }
}
