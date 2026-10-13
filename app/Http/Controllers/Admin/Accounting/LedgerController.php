<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Admin\Accounting\Concerns\HandlesEntries;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\AccountTransaction;
use Illuminate\Http\Request;

/** Read-only list of every entry of every account. Entries are added / deleted from their own pages. */
class LedgerController extends Controller
{
    use HandlesEntries; // only used for accountChoices() (Cash, banks, bKash, Nagad)

    public function index(Request $request)
    {
        $accounts = $this->accountChoices();
        $ids      = array_column($accounts, 'id');
        $sources  = self::sources();

        $date = fn ($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? (string) $v : '';
        $acc  = (int) $request->query('account');
        $cat  = (string) $request->query('category');

        $f = [
            'from'     => $date($request->query('from')),
            'to'       => $date($request->query('to')),
            'account'  => in_array($acc, $ids, true) ? $acc : '',
            'dir'      => in_array($request->query('dir'), ['in', 'out'], true) ? $request->query('dir') : '',
            'type'     => isset($sources[(string) $request->query('type')]) ? (string) $request->query('type') : '',
            'category' => ($cat === 'none' || ctype_digit($cat)) ? $cat : '',
            'q'        => mb_substr(trim((string) $request->query('q')), 0, 100),
        ];
        $filtered = count(array_filter($f, fn ($v) => $v !== '')) > 0;

        $apply = function ($q) use ($f) {
            if ($f['from'] !== '')    $q->whereDate('txn_date', '>=', $f['from']);
            if ($f['to'] !== '')      $q->whereDate('txn_date', '<=', $f['to']);
            if ($f['account'] !== '') $q->where('account_id', $f['account']);
            if ($f['dir'] !== '')     $q->where('direction', $f['dir']);
            if ($f['type'] !== '')    $q->where('source', $f['type']);
            if ($f['category'] === 'none') {
                $q->whereNull('category_id');
            } elseif ($f['category'] !== '') {
                $q->where('category_id', (int) $f['category']);
            }
            if ($f['q'] !== '') {
                $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $f['q']) . '%';
                $q->where('note', 'like', $like);
            }

            return $q;
        };

        $transactions = $apply(AccountTransaction::query())->with(['account', 'category'])
            ->orderByDesc('txn_date')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        return view('admin.accounting.ledger.index', [
            'accounts'     => $accounts,
            'categories'   => AccountCategory::orderBy('name')->get()->groupBy('type'),
            'sources'      => $sources,
            'transactions' => $transactions,
            'f'            => $f,
            'filtered'     => $filtered,
            'running'      => $this->runningBalances($f, $transactions),
            'totalIn'      => (float) $apply(AccountTransaction::query())->where('direction', 'in')->sum('amount'),
            'totalOut'     => (float) $apply(AccountTransaction::query())->where('direction', 'out')->sum('amount'),
        ]);
    }

    /** source => label */
    public static function sources(): array
    {
        return ['manual' => 'Manual', 'income' => 'Income', 'expense' => 'Expense', 'transfer' => 'Transfer', 'holding' => 'Savings & Loans'];
    }

    /**
     * "Balance after this entry" for each row of the page. Only possible when the list shows one whole account
     * (an account is chosen and no other filter is on). Returns [entry id => balance], or null.
     */
    private function runningBalances(array $f, $transactions): ?array
    {
        $others = array_filter(array_diff_key($f, ['account' => 1]), fn ($v) => $v !== '');

        if ($f['account'] === '' || $others || $transactions->isEmpty()) {
            return null;
        }

        $account = Account::find($f['account']);
        if (!$account) {
            return null;
        }

        // balance after the first (newest) row of this page = opening + everything up to and including that row
        $first = $transactions->first();
        $day   = $first->txn_date->toDateString();

        $upTo = fn () => AccountTransaction::query()->where('account_id', $account->id)
            ->where(function ($q) use ($day, $first) {
                $q->whereDate('txn_date', '<', $day)
                  ->orWhere(function ($q2) use ($day, $first) {
                      $q2->whereDate('txn_date', $day)->where('id', '<=', $first->id);
                  });
            });

        $balance = (float) $account->opening_balance
            + (float) $upTo()->where('direction', 'in')->sum('amount')
            - (float) $upTo()->where('direction', 'out')->sum('amount');

        $out = [];
        foreach ($transactions as $tx) {
            $out[$tx->id] = round($balance, 2);
            $balance -= $tx->isIn() ? (float) $tx->amount : -(float) $tx->amount; // step back to the row below
        }

        return $out;
    }
}
