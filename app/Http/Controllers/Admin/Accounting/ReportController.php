<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Admin\Accounting\Concerns\HandlesPeriod;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\AccountTransaction;
use Illuminate\Http\Request;

/**
 * Reports read only the accounts (account_transactions). Money moved between your own accounts
 * (transfers) is never income or expense, so every report leaves transfers out, except the
 * per-account cash flow table, which shows them so the balances add up.
 */
class ReportController extends Controller
{
    use HandlesPeriod;

    // ---------- Income / Expense report ----------

    public function income(Request $request)
    {
        return $this->entryReport($request, 'income');
    }

    public function expense(Request $request)
    {
        return $this->entryReport($request, 'expense');
    }

    private function entryReport(Request $request, string $kind)
    {
        $p     = $this->period($request);
        $base  = fn () => $this->inRange(AccountTransaction::query()->where('source', $kind), $p);
        $total = (float) $base()->sum('amount');
        $count = (int) $base()->count();

        return view('admin.accounting.reports.entries', $this->periodView($p, 'admin.accounting.reports.' . $kind) + [
            'kind'      => $kind,
            'label'     => $kind === 'income' ? 'Income' : 'Expense',
            'total'     => $total,
            'count'     => $count,
            'average'   => $count ? $total / $count : 0.0,
            'byCategory' => $this->byCategory($base(), $total),
            'byAccount' => $this->byAccount($base(), $total),
            'biggest'   => $base()->with(['account', 'category'])->orderByDesc('amount')->orderByDesc('id')->limit(10)->get(),
        ]);
    }

    // ---------- Profit & Loss ----------

    public function profitLoss(Request $request)
    {
        $p   = $this->period($request);
        $sum = fn ($source, $dir = null) => (float) $this->inRange(
            AccountTransaction::query()->where('source', $source)->when($dir, fn ($q) => $q->where('direction', $dir)), $p
        )->sum('amount');

        $income  = $sum('income');
        $expense = $sum('expense');

        return view('admin.accounting.reports.profit_loss', $this->periodView($p, 'admin.accounting.reports.profit_loss') + [
            'income'        => $income,
            'expense'       => $expense,
            'profit'        => $income - $expense,
            'incomeRows'    => $this->byCategory($this->inRange(AccountTransaction::query()->where('source', 'income'), $p), $income),
            'expenseRows'   => $this->byCategory($this->inRange(AccountTransaction::query()->where('source', 'expense'), $p), $expense),
            'manualIn'      => $sum('manual', 'in'),   // typed straight into an account page: not counted as profit
            'manualOut'     => $sum('manual', 'out'),
        ]);
    }

    // ---------- Cash flow ----------

    public function cashFlow(Request $request)
    {
        $p = $this->period($request);

        // --- per account: opening → in / out / transfers → closing ---
        $accounts = Account::orderByRaw("CASE type WHEN 'cash' THEN 0 WHEN 'bank' THEN 1 WHEN 'bkash' THEN 2 ELSE 3 END")
            ->orderBy('name')->orderBy('id')->get();

        $before = [];
        if ($p['from']) {
            foreach (AccountTransaction::query()->whereDate('txn_date', '<', $p['from'])
                ->selectRaw('account_id, direction, SUM(amount) as s')->groupBy('account_id', 'direction')->get() as $r) {
                $before[$r->account_id][$r->direction] = (float) $r->s;
            }
        }

        $during = [];
        $isT    = "CASE WHEN source = 'transfer' THEN 1 ELSE 0 END";
        foreach ($this->inRange(AccountTransaction::query(), $p)
            ->selectRaw("account_id, direction, {$isT} as is_t, SUM(amount) as s")
            ->groupBy('account_id', 'direction')->groupByRaw($isT)->get() as $r) {
            $during[$r->account_id][$r->direction][(int) $r->is_t] = (float) $r->s;
        }

        $rows = $accounts->map(function ($a) use ($before, $during, $p) {
            $open = (float) $a->opening_balance + ($p['from'] ? (($before[$a->id]['in'] ?? 0) - ($before[$a->id]['out'] ?? 0)) : 0);
            $in   = $during[$a->id]['in'][0]  ?? 0.0;
            $out  = $during[$a->id]['out'][0] ?? 0.0;
            $tIn  = $during[$a->id]['in'][1]  ?? 0.0;
            $tOut = $during[$a->id]['out'][1] ?? 0.0;

            return ['account' => $a, 'open' => $open, 'in' => $in, 'out' => $out, 'tIn' => $tIn, 'tOut' => $tOut,
                    'close' => $open + $in - $out + $tIn - $tOut];
        });

        // --- by month (transfers left out) ---
        $months = [];
        foreach ($this->inRange(AccountTransaction::query()->where('source', '!=', 'transfer'), $p)
            ->orderBy('txn_date')->cursor() as $tx) {
            $k = $tx->txn_date->format('Y-m');
            $months[$k] ??= ['label' => $tx->txn_date->format('M Y'), 'in' => 0.0, 'out' => 0.0];
            $months[$k][$tx->direction === 'in' ? 'in' : 'out'] += (float) $tx->amount;
        }

        return view('admin.accounting.reports.cash_flow', $this->periodView($p, 'admin.accounting.reports.cash_flow') + [
            'rows'   => $rows,
            'open'   => (float) $rows->sum('open'),
            'in'     => (float) $rows->sum('in'),
            'out'    => (float) $rows->sum('out'),
            'close'  => (float) $rows->sum('close'),
            'months' => $months,
        ]);
    }

    // ---------- Customer due ----------

    public function customerDue()
    {
        // "who owes us" is the Customer Due page itself
        return redirect()->route('admin.accounting.customers.due');
    }

    // ---------- helpers ----------

    private function inRange($query, array $p)
    {
        if ($p['from']) $query->whereDate('txn_date', '>=', $p['from']);
        if ($p['to'])   $query->whereDate('txn_date', '<=', $p['to']);

        return $query;
    }

    /** Variables the period bar of every report page needs */
    private function periodView(array $p, string $route): array
    {
        return [
            'range' => $p['range'], 'ranges' => $p['labels'], 'from' => $p['from'], 'to' => $p['to'],
            'title' => $p['title'], 'span' => $p['span'], 'route' => $route,
        ];
    }

    /** [[id|null, name, amount, count, percent]] biggest first */
    private function byCategory($query, float $total): array
    {
        $names = AccountCategory::pluck('name', 'id');

        return $query->selectRaw('category_id, SUM(amount) as s, COUNT(*) as c')->groupBy('category_id')->get()
            ->map(fn ($r) => [
                'id'      => $r->category_id,
                'name'    => $r->category_id ? ($names[$r->category_id] ?? 'Deleted category') : 'No category',
                'amount'  => (float) $r->s,
                'count'   => (int) $r->c,
                'percent' => $total > 0 ? round($r->s / $total * 100, 1) : 0.0,
            ])->sortByDesc('amount')->values()->all();
    }

    private function byAccount($query, float $total): array
    {
        $names = Account::pluck('name', 'id');

        return $query->selectRaw('account_id, SUM(amount) as s, COUNT(*) as c')->groupBy('account_id')->get()
            ->map(fn ($r) => [
                'name'    => $names[$r->account_id] ?? 'Account',
                'amount'  => (float) $r->s,
                'count'   => (int) $r->c,
                'percent' => $total > 0 ? round($r->s / $total * 100, 1) : 0.0,
            ])->sortByDesc('amount')->values()->all();
    }
}
