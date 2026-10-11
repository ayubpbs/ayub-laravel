<?php

namespace App\Http\Controllers\Admin\Accounting\Concerns;

use App\Models\Account;
use Illuminate\Http\Request;

/** Ledger logic shared by the Cash / bKash / Nagad pages and the Bank pages */
trait HandlesLedger
{
    protected function ledgerData(Request $request, Account $account): array
    {
        $date = fn ($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? (string) $v : '';
        $f = [
            'from' => $date($request->query('from')),
            'to'   => $date($request->query('to')),
            'dir'  => in_array($request->query('dir'), ['in', 'out'], true) ? $request->query('dir') : '',
        ];
        $filtered = $f['from'] !== '' || $f['to'] !== '' || $f['dir'] !== '';

        $apply = function ($q) use ($f) {
            if ($f['from'] !== '') $q->whereDate('txn_date', '>=', $f['from']);
            if ($f['to'] !== '')   $q->whereDate('txn_date', '<=', $f['to']);
            if ($f['dir'] !== '')  $q->where('direction', $f['dir']);

            return $q;
        };

        $transactions = $apply($account->transactions())
            ->orderByDesc('txn_date')->orderByDesc('id')
            ->paginate(20)->withQueryString();

        return [
            'account'      => $account,
            'totals'       => $account->totals(),
            'transactions' => $transactions,
            'f'            => $f,
            'filtered'     => $filtered,
            'period'       => [
                'in'  => (float) $apply($account->transactions())->where('direction', 'in')->sum('amount'),
                'out' => (float) $apply($account->transactions())->where('direction', 'out')->sum('amount'),
            ],
        ];
    }

    protected function addEntry(Request $request, Account $account): void
    {
        $data = $request->validate([
            'direction' => 'required|in:in,out',
            'amount'    => 'required|numeric|min:0.01|max:99999999',
            'txn_date'  => 'required|date',
            'note'      => 'nullable|string|max:255',
        ], [
            'direction.required' => 'Please choose Money In or Money Out.',
            'amount.required'    => 'Please enter the amount.',
            'amount.min'         => 'The amount must be greater than zero.',
            'txn_date.required'  => 'Please choose the date.',
        ]);

        $account->transactions()->create([
            'direction' => $data['direction'],
            'amount'    => $data['amount'],
            'txn_date'  => $data['txn_date'],
            'note'      => isset($data['note']) ? trim($data['note']) : null,
            'source'    => 'manual',
        ]);
    }

    /** Returns an error message, or null when the entry was deleted */
    protected function removeEntry(Account $account, $id): ?string
    {
        $tx = $account->transactions()->findOrFail($id);

        // Entries created by other modules later (income, expense, transfer) must be changed from there
        if ($tx->source !== 'manual') {
            return 'This entry was created by another module. Change it from there.';
        }

        $tx->delete();

        return null;
    }

    protected function saveOpening(Request $request, Account $account): void
    {
        $data = $request->validate([
            'opening_balance' => 'required|numeric|min:0|max:99999999',
        ], [
            'opening_balance.required' => 'Please enter the opening balance.',
        ]);

        $account->update(['opening_balance' => $data['opening_balance']]);
    }
}
