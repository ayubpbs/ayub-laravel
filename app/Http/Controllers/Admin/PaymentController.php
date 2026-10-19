<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    // ---------- Lists ----------

    public function index(Request $request)
    {
        $tab = $request->query('tab') === 'due' ? 'due' : 'payments';

        $summary = $this->summary();

        if ($tab === 'due') {
            $status = (string) $request->query('status', '');
            $q      = trim((string) $request->query('q', ''));

            $query = Order::withPaid()->whereNotIn('status', Order::lockedStatuses())->owing()->oldest();
            if ($status !== '' && array_key_exists($status, Order::statuses())) {
                $query->where('status', $status);
            } else {
                $status = '';
            }
            if ($q !== '') {
                $query->where(function ($w) use ($q) {
                    $w->where('name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%")->orWhere('id', ltrim($q, '#'));
                });
            }

            $orders = $query->paginate(25)->withQueryString();

            return view('admin.payments.index', compact('tab', 'summary', 'orders', 'status', 'q'));
        }

        $f        = $this->filters($request);
        $payments = $this->filtered($f)->paginate(25)->withQueryString();

        return view('admin.payments.index', ['tab' => $tab, 'summary' => $summary, 'payments' => $payments, 'f' => $f]);
    }

    public function export(Request $request)
    {
        $rows = $this->filtered($this->filters($request))->limit(5000)->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // so Excel reads Bengali names correctly
            fputcsv($out, ['Date', 'Order', 'Customer', 'Mobile', 'Type', 'Method', 'Amount', 'Transaction ID', 'Sender number', 'Note']);
            foreach ($rows as $p) {
                fputcsv($out, [
                    $p->paid_at->format('Y-m-d H:i'),
                    '#' . $p->order_id,
                    optional($p->order)->name,
                    optional($p->order)->phone,
                    $p->isRefund() ? 'Refund' : 'Payment',
                    $p->methodLabel(),
                    ($p->isRefund() ? '-' : '') . $p->amount,
                    $p->trx_id,
                    $p->sender_number,
                    $p->note,
                ]);
            }
            fclose($out);
        }, 'payments-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filters(Request $request): array
    {
        return [
            'q'      => trim((string) $request->query('q', '')),
            'method' => (string) $request->query('method', ''),
            'type'   => (string) $request->query('type', ''),
            'from'   => (string) $request->query('from', ''),
            'to'     => (string) $request->query('to', ''),
        ];
    }

    private function filtered(array $f)
    {
        $query = Payment::with('order')->orderByDesc('paid_at')->orderByDesc('id');

        if ($f['q'] !== '') {
            $q = $f['q'];
            $query->where(function ($w) use ($q) {
                $w->where('trx_id', 'like', "%{$q}%")
                  ->orWhere('sender_number', 'like', "%{$q}%")
                  ->orWhere('order_id', ltrim($q, '#'))
                  ->orWhereHas('order', fn ($o) => $o->where('name', 'like', "%{$q}%")->orWhere('phone', 'like', "%{$q}%"));
            });
        }
        if ($f['method'] !== '' && array_key_exists($f['method'], Payment::methods())) {
            $query->where('method', $f['method']);
        }
        if (in_array($f['type'], ['payment', 'refund'], true)) {
            $query->where('type', $f['type']);
        }
        if ($this->validDate($f['from'])) {
            $query->whereDate('paid_at', '>=', $f['from']);
        }
        if ($this->validDate($f['to'])) {
            $query->whereDate('paid_at', '<=', $f['to']);
        }

        return $query;
    }

    private function validDate(string $d): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
    }

    private function summary(): array
    {
        $net = function ($query) {
            return (int) (clone $query)->where('type', 'payment')->sum('amount') - (int) (clone $query)->where('type', 'refund')->sum('amount');
        };

        $locked  = Order::lockedStatuses();
        $netSql  = Order::netPaidSql();

        return [
            'today' => $net(Payment::whereDate('paid_at', today())),
            'month' => $net(Payment::where('paid_at', '>=', now()->startOfMonth())),
            // What customers still owe on live orders
            'due'   => (int) Order::whereNotIn('status', $locked)->owing()
                ->selectRaw("COALESCE(SUM(orders.total - {$netSql}), 0) as d")->value('d'),
            'due_orders' => Order::whereNotIn('status', $locked)->owing()->count(),
            // Money we hold for cancelled / returned orders and still have to give back
            'refund_due' => (int) Order::whereIn('status', $locked)->whereRaw("{$netSql} > 0")
                ->selectRaw("COALESCE(SUM({$netSql}), 0) as d")->value('d'),
        ];
    }

    // ---------- Add a payment / refund ----------

    public function create(Request $request)
    {
        $order = null;
        $lookup = trim((string) $request->query('order', ''));
        if ($lookup !== '') {
            $order = Order::with('payments')->find((int) ltrim($lookup, '#'));
            if (!$order) {
                return redirect()->route('admin.payments.create')->with('error', 'Order "' . $lookup . '" was not found.');
            }
        }

        return view('admin.payments.create', compact('order'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'order_id'      => 'required|exists:orders,id',
            'type'          => 'required|in:payment,refund',
            'method'        => ['required', Rule::in(array_keys(Payment::methods()))],
            'amount'        => 'required|integer|min:1|max:10000000',
            'trx_id'        => ['nullable', 'string', 'max:100', Rule::unique('payments', 'trx_id')],
            'sender_number' => 'nullable|string|max:20',
            'note'          => 'nullable|string|max:255',
            'paid_at'       => 'nullable|date',
        ], [
            'amount.required' => 'Please enter the amount.',
            'amount.integer'  => 'The amount must be a whole number of taka.',
            'trx_id.unique'   => 'This transaction ID has already been recorded.',
        ]);

        $data['trx_id'] = isset($data['trx_id']) && trim($data['trx_id']) !== '' ? trim($data['trx_id']) : null;

        if ($data['type'] === 'payment' && in_array($data['method'], Payment::mobileMethods(), true) && !$data['trx_id']) {
            return back()->withInput()->with('error', 'Please enter the transaction ID for ' . Payment::methods()[$data['method']] . ' payments.');
        }

        $error = DB::transaction(function () use ($data) {
            // Lock the order so two people cannot record the same money twice at the same moment
            $order = Order::lockForUpdate()->findOrFail($data['order_id']);
            $net   = $order->netPaid();

            if ($data['type'] === 'payment') {
                if ($order->isLocked()) {
                    return 'Order #' . $order->id . ' is ' . strtolower($order->statusLabel()) . ', so it cannot receive payments.';
                }
                $due = (int) $order->total - $net;
                if ($data['amount'] > $due) {
                    return $due <= 0
                        ? 'Order #' . $order->id . ' is already fully paid.'
                        : 'The amount is more than the due ৳ ' . number_format($due) . ' for order #' . $order->id . '.';
                }
            } elseif ($data['amount'] > $net) {
                return 'You can refund at most ৳ ' . number_format(max(0, $net)) . ' for order #' . $order->id . ' (the amount received so far).';
            }

            $payment = Payment::create([
                'order_id'      => $order->id,
                'type'          => $data['type'],
                'method'        => $data['method'],
                'amount'        => $data['amount'],
                'trx_id'        => $data['trx_id'],
                'sender_number' => $data['sender_number'] ?? null,
                'note'          => $data['note'] ?? null,
                'paid_at'       => !empty($data['paid_at']) ? $data['paid_at'] : now(),
            ]);

            \App\Support\Notify::payment($payment->setRelation('order', $order));

            return null;
        });

        if ($error) {
            return back()->withInput()->with('error', $error);
        }

        $msg = ($data['type'] === 'refund' ? 'Refund' : 'Payment') . ' of ৳ ' . number_format($data['amount']) . ' recorded for order #' . $data['order_id'] . '.';

        return $request->input('return') === 'order'
            ? redirect()->route('admin.orders.show', $data['order_id'])->with('success', $msg)
            : redirect()->route('admin.payments.index')->with('success', $msg);
    }

    public function destroy($id)
    {
        $payment = Payment::findOrFail($id);
        $orderId = $payment->order_id;
        $payment->delete();

        return back()->with('success', 'Payment record deleted from order #' . $orderId . '.');
    }

    // ---------- One-click "cash collected on delivery" ----------

    public function collect($id)
    {
        $r = $this->collectOne((int) $id);

        return back()->with($r['ok'] ? 'success' : 'error', $r['message']);
    }

    public function collectBulk(Request $request)
    {
        $ids = array_slice(array_unique(array_map('intval', (array) $request->input('ids', []))), 0, 50);
        if (!$ids) {
            return back()->with('error', 'Please tick at least one order.');
        }

        $done = 0; $total = 0; $fail = [];
        foreach ($ids as $id) {
            $r = $this->collectOne($id);
            if ($r['ok']) { $done++; $total += $r['amount']; } else { $fail[] = $r['message']; }
        }

        $msg = $done ? "{$done} order(s) marked as paid (৳ " . number_format($total) . ' recorded).' : '';
        if ($fail) {
            return back()->with($done ? 'success' : 'error', trim($msg . ' ' . implode(' ', $fail)));
        }

        return back()->with('success', $msg);
    }

    private function collectOne(int $id): array
    {
        return DB::transaction(function () use ($id) {
            $order = Order::lockForUpdate()->find($id);
            if (!$order) {
                return ['ok' => false, 'amount' => 0, 'message' => 'Order #' . $id . ' not found.'];
            }
            if ($order->isLocked()) {
                return ['ok' => false, 'amount' => 0, 'message' => 'Order #' . $id . ' is ' . strtolower($order->statusLabel()) . '.'];
            }
            $due = (int) $order->total - $order->netPaid();
            if ($due <= 0) {
                return ['ok' => false, 'amount' => 0, 'message' => 'Order #' . $id . ' is already fully paid.'];
            }

            $payment = Payment::create([
                'order_id' => $order->id,
                'type'     => 'payment',
                'method'   => 'cod',
                'amount'   => $due,
                'note'     => 'Cash on delivery collected',
                'paid_at'  => now(),
            ]);
            \App\Support\Notify::payment($payment->setRelation('order', $order));

            return ['ok' => true, 'amount' => $due, 'message' => 'Order #' . $id . ' marked as paid.'];
        });
    }
}
