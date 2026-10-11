<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Admin\Accounting\Concerns\HandlesEntries;
use App\Http\Controllers\Controller;
use App\Models\AccountCategory;
use App\Models\AccountTransaction;
use App\Models\Order;
use App\Models\Payment;
use App\Support\PaymentLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CustomerDueController extends Controller
{
    use HandlesEntries; // accountChoices()

    /** What customers still owe, one row per customer (grouped by mobile number) */
    public function due(Request $request)
    {
        $q = trim((string) $request->query('q', ''));

        $query = Order::withPaid()->whereNotIn('status', Order::lockedStatuses())->owing()->oldest();
        if ($q !== '') {
            $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
            $query->where(function ($w) use ($like) {
                $w->where('name', 'like', $like)->orWhere('phone', 'like', $like);
            });
        }

        $orders = $query->limit(2000)->get();

        $customers = $orders->groupBy(fn ($o) => preg_replace('/\D+/', '', (string) $o->phone) ?: 'order-' . $o->id)
            ->map(function ($group) {
                $last = $group->last(); // newest order = the name the customer uses now

                return [
                    'name'   => $last->name,
                    'phone'  => $last->phone,
                    'orders' => $group->values(),
                    'due'    => (int) $group->sum(fn ($o) => $o->dueAmount()),
                    'oldest' => $group->first()->created_at,
                ];
            })
            ->sortByDesc('due')->values();

        $waiting = PaymentLedger::pending()->get();

        return view('admin.accounting.customers.due', [
            'q'         => $q,
            'customers' => $customers,
            'totalDue'  => (int) $customers->sum('due'),
            'orderCount' => $orders->count(),
            // customers already paid on delivery, but the courier has not handed the money over yet
            'withCourier'      => (int) $waiting->where('method', 'cod')->where('type', 'payment')->sum('amount'),
            'withCourierCount' => $waiting->where('method', 'cod')->where('type', 'payment')->count(),
        ]);
    }

    /** Payments / refunds that are not in any account yet (courier money, Rocket, …) */
    public function receive()
    {
        $pending = PaymentLedger::pending()->orderBy('paid_at')->orderBy('id')->get();

        $recent = AccountTransaction::with('account')->whereNotNull('payment_id')
            ->orderByDesc('id')->limit(10)->get();

        return view('admin.accounting.customers.receive', [
            'ready'    => PaymentLedger::ready(),
            'defaultAccount' => (int) \App\Models\Setting::get('acct_default_account', 0),
            'courierCharge'  => (int) \App\Models\Setting::get('acct_courier_charge', 0),
            'pending'  => $pending,
            'accounts' => $this->accountChoices(),
            'recent'   => $recent,
            'cod'      => (int) $pending->where('method', 'cod')->where('type', 'payment')->sum('amount'),
            'other'    => (int) $pending->where('type', 'payment')->where('method', '!=', 'cod')->sum('amount'),
            'refunds'  => (int) $pending->where('type', 'refund')->sum('amount'),
        ]);
    }

    /** Put the ticked payments / refunds into one account */
    public function storeReceive(Request $request)
    {
        $ids = array_column($this->accountChoices(), 'id');

        $data = $request->validate([
            'ids'        => 'required|array|min:1|max:200',
            'ids.*'      => 'integer',
            'account_id' => ['required', Rule::in($ids)],
            'txn_date'   => 'required|date',
            'courier_charge' => 'nullable|numeric|min:0|max:10000000',
        ], [
            'ids.required'        => 'Please tick at least one payment.',
            'ids.min'             => 'Please tick at least one payment.',
            'account_id.required' => 'Please choose the account.',
            'account_id.in'       => 'Please choose a valid account.',
            'txn_date.required'   => 'Please choose the date.',
            'courier_charge.numeric' => 'The courier charge must be a number.',
        ]);

        $charge = round((float) ($data['courier_charge'] ?? 0), 2);
        $account = \App\Models\Account::findOrFail($data['account_id']);

        $in = 0; $out = 0; $n = 0;
        DB::transaction(function () use ($data, $account, $charge, &$in, &$out, &$n) {
            // only payments that are still waiting (so a double click cannot count the money twice)
            $payments = PaymentLedger::pending()->whereIn('id', array_map('intval', $data['ids']))->lockForUpdate()->get();

            foreach ($payments as $p) {
                PaymentLedger::post($p, $account, $data['txn_date']);
                $p->isRefund() ? $out += $p->amount : $in += $p->amount;
                $n++;
            }

            // charge the courier kept: one expense on the same account, so the account shows what really arrived
            if ($n > 0 && $charge > 0) {
                if ($charge > $in) {
                    throw ValidationException::withMessages(['courier_charge' => 'The courier charge cannot be more than the money received (৳ ' . number_format($in) . ').']);
                }
                AccountTransaction::create([
                    'account_id'  => $account->id,
                    'direction'   => 'out',
                    'amount'      => $charge,
                    'txn_date'    => $data['txn_date'],
                    'note'        => 'Courier charge deducted · ' . $n . ' payment(s)',
                    'category_id' => AccountCategory::firstOrCreate(['type' => 'expense', 'name' => 'Courier charge'])->id,
                    'source'      => 'expense',
                ]);
            }
        });

        if ($n === 0) {
            return back()->with('error', 'Nothing was added. These payments may already be in an account.');
        }

        return redirect()->route('admin.accounting.customers.receive')
            ->with('success', $n . ' payment(s) added to ' . $account->name . ': ৳ ' . number_format($in) . ' in' . ($out ? ', ৳ ' . number_format($out) . ' out (refunds)' : '') . ($charge > 0 ? ', ৳ ' . number_format($charge, 2) . ' courier charge' : '') . '.');
    }
}
