<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Admin\Accounting\Concerns\HandlesPeriod;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountTransaction;
use App\Models\Holding;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

class DashboardController extends Controller
{
    use HandlesPeriod;

    public function index(Request $request)
    {
        $p = $this->period($request);

        // ---- accounts, in display order: Cash, every bank, bKash, Nagad ----
        $single = [];
        foreach (array_keys(Account::types()) as $t) {
            $single[$t] = Account::forType($t);
        }
        $banks = Account::where('type', 'bank')->orderBy('name')->orderBy('id')->get();

        $list = collect([$single['cash']])->merge($banks)->push($single['bkash'])->push($single['nagad']);

        $allTime = $this->sums(null, null);
        $period  = $this->sums($p['from'], $p['to']);
        $moved   = collect($this->sums($p['from'], $p['to'], true)); // without transfers: they only move money between accounts

        $rows = $list->map(function ($a) use ($allTime, $period) {
            $t = $allTime[$a->id] ?? ['in' => 0.0, 'out' => 0.0];
            $r = $period[$a->id] ?? ['in' => 0.0, 'out' => 0.0];

            return [
                'account' => $a,
                'balance' => (float) $a->opening_balance + $t['in'] - $t['out'],
                'in'      => $r['in'],
                'out'     => $r['out'],
            ];
        });

        $byType = fn ($type) => (float) $rows->filter(fn ($r) => $r['account']->type === $type)->sum('balance');

        // ---- last 6 months ----
        $start  = now()->startOfMonth()->subMonths(5);
        $months = [];
        for ($i = 0; $i < 6; $i++) {
            $m = $start->copy()->addMonths($i);
            $months[$m->format('Y-m')] = ['label' => $m->format('M Y'), 'in' => 0.0, 'out' => 0.0];
        }
        foreach (AccountTransaction::where('txn_date', '>=', $start->toDateString())->where('source', '!=', 'transfer')->get(['txn_date', 'direction', 'amount']) as $tx) {
            $k = $tx->txn_date->format('Y-m');
            if (isset($months[$k])) {
                $months[$k][$tx->direction === 'in' ? 'in' : 'out'] += (float) $tx->amount;
            }
        }

        $recent = AccountTransaction::with('account')->orderByDesc('txn_date')->orderByDesc('id')->limit(10)->get();

        return view('admin.accounting.dashboard', [
            'range'    => $p['range'],
            'ranges'   => $p['labels'],
            'from'     => $p['from'],
            'to'       => $p['to'],
            'title'    => $p['title'],
            'span'     => $p['span'],
            'rows'     => $rows,
            'total'    => (float) $rows->sum('balance'),
            'cash'     => $byType('cash'),
            'bank'     => $byType('bank'),
            'bankCount' => $banks->count(),
            'bkash'    => $byType('bkash'),
            'nagad'    => $byType('nagad'),
            'periodIn' => (float) $moved->sum('in'),
            'periodOut' => (float) $moved->sum('out'),
            'months'   => $months,
            'recent'   => $recent,
            'shop'     => $this->shop($p['from'], $p['to']),
            'wealth'   => $this->wealth(),
            'stock'    => $this->stockValue(),
        ]);
    }

    /** Goods on the shelf at buying price. Null if the cost_price column does not exist yet (migration not run). */
    private function stockValue(): ?array
    {
        try {
            return \Illuminate\Support\Facades\Schema::hasColumn('products', 'cost_price') ? Product::stockSummary() : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /** Savings certificates, FDR, loans and receivables. Null if the table is not created yet (migration not run). */
    private function wealth(): ?array
    {
        try {
            return Holding::summary();
        } catch (Throwable $e) {
            return null;
        }
    }

    /** account_id => [in, out] for a date range (null = no limit) */
    private function sums(?string $from, ?string $to, bool $skipTransfers = false): array
    {
        $q = AccountTransaction::query()
            ->selectRaw('account_id, direction, SUM(amount) as s')
            ->groupBy('account_id', 'direction');

        if ($from) $q->whereDate('txn_date', '>=', $from);
        if ($to)   $q->whereDate('txn_date', '<=', $to);
        if ($skipTransfers) $q->where('source', '!=', 'transfer');

        $out = [];
        foreach ($q->get() as $row) {
            $out[$row->account_id][$row->direction === 'in' ? 'in' : 'out'] = (float) $row->s;
        }
        foreach ($out as $id => $v) {
            $out[$id] = ['in' => $v['in'] ?? 0.0, 'out' => $v['out'] ?? 0.0];
        }

        return $out;
    }

    /** Customer side of the shop (orders & payments), same numbers as the Payments page. Null if unavailable. */
    private function shop(?string $from, ?string $to): ?array
    {
        try {
            $locked = Order::lockedStatuses();
            $netSql = Order::NET_PAID_SQL;

            $recv = Payment::query();
            if ($from) $recv->whereDate('paid_at', '>=', $from);
            if ($to)   $recv->whereDate('paid_at', '<=', $to);

            return [
                'received'   => (int) (clone $recv)->where('type', 'payment')->sum('amount') - (int) (clone $recv)->where('type', 'refund')->sum('amount'),
                'due'        => (int) Order::whereNotIn('status', $locked)->owing()
                    ->selectRaw("COALESCE(SUM(orders.total - {$netSql}), 0) as d")->value('d'),
                'due_orders' => Order::whereNotIn('status', $locked)->owing()->count(),
                'refund_due' => (int) Order::whereIn('status', $locked)->whereRaw("{$netSql} > 0")
                    ->selectRaw("COALESCE(SUM({$netSql}), 0) as d")->value('d'),
            ];
        } catch (Throwable $e) {
            return null;
        }
    }
}
