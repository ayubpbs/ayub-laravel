<?php

namespace App\Http\Controllers\Admin\Accounting\Concerns;

use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\AccountTransaction;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Logic shared by the Income pages and the Expense pages (both are rows of account_transactions) */
trait HandlesEntries
{
    /** kind => settings. "dir" is the direction saved in account_transactions. */
    protected function kinds(): array
    {
        return [
            'income'  => ['dir' => 'in',  'label' => 'Income',  'list' => 'All Income',   'add' => 'Add Income',  'icon' => '📥'],
            'expense' => ['dir' => 'out', 'label' => 'Expense', 'list' => 'All Expenses', 'add' => 'Add Expense', 'icon' => '📤'],
        ];
    }

    /** Every account the money can go into / come out of, in display order: Cash, banks, bKash, Nagad */
    protected function accountChoices(): array
    {
        $types = Account::types();
        $out   = [];

        $out[] = $this->choice(Account::forType('cash'), $types['cash'][1] . ' ' . $types['cash'][0]);

        foreach (Account::where('type', 'bank')->orderBy('name')->orderBy('id')->get() as $b) {
            $out[] = $this->choice($b, '🏦 ' . $b->name . ($b->account_no ? ' (' . $b->account_no . ')' : ''));
        }

        foreach (['bkash', 'nagad'] as $t) {
            $out[] = $this->choice(Account::forType($t), $types[$t][1] . ' ' . $types[$t][0]);
        }

        return $out;
    }

    private function choice(Account $a, string $label): array
    {
        return ['id' => $a->id, 'label' => $label];
    }

    protected function entryIndex(Request $request, string $kind)
    {
        $k        = $this->kinds()[$kind];
        $accounts = $this->accountChoices();
        $ids      = array_column($accounts, 'id');

        $date = fn ($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? (string) $v : '';
        $acc  = (int) $request->query('account');
        $cat  = (string) $request->query('category');

        $f = [
            'from'     => $date($request->query('from')),
            'to'       => $date($request->query('to')),
            'account'  => in_array($acc, $ids, true) ? $acc : '',
            'category' => ($cat === 'none' || ctype_digit($cat)) ? $cat : '',
        ];
        $filtered = $f['from'] !== '' || $f['to'] !== '' || $f['account'] !== '' || $f['category'] !== '';

        $apply = function ($q) use ($f) {
            if ($f['from'] !== '')     $q->whereDate('txn_date', '>=', $f['from']);
            if ($f['to'] !== '')       $q->whereDate('txn_date', '<=', $f['to']);
            if ($f['account'] !== '')  $q->where('account_id', $f['account']);
            if ($f['category'] === 'none') {
                $q->whereNull('category_id');
            } elseif ($f['category'] !== '') {
                $q->where('category_id', (int) $f['category']);
            }

            return $q;
        };

        $base = fn () => AccountTransaction::query()->where('source', $kind);

        $entries = $apply($base())->with(['account', 'category'])
            ->orderByDesc('txn_date')->orderByDesc('id')
            ->paginate(20)->withQueryString();

        return view('admin.accounting.entries.index', [
            'kind'       => $kind,
            'k'          => $k,
            'entries'    => $entries,
            'accounts'   => $accounts,
            'categories' => AccountCategory::ofType($kind)->orderBy('name')->get(),
            'f'          => $f,
            'filtered'   => $filtered,
            'listTotal'  => (float) $apply($base())->sum('amount'),
            'monthTotal' => (float) $base()->whereDate('txn_date', '>=', now()->startOfMonth()->toDateString())->sum('amount'),
            'allTotal'   => (float) $base()->sum('amount'),
        ]);
    }

    protected function entryCreate(string $kind, $entry = null)
    {
        return view('admin.accounting.entries.create', [
            'entry'      => $entry,
            'kind'       => $kind,
            'k'          => $this->kinds()[$kind],
            'accounts'   => $this->accountChoices(),
            'categories' => AccountCategory::ofType($kind)->orderBy('name')->get(),
        ]);
    }

    /** Validated form data (the same rules for adding and editing) */
    private function entryData(Request $request, string $kind): array
    {
        $ids = array_column($this->accountChoices(), 'id');

        return $request->validate([
            'account_id' => ['required', Rule::in($ids)],
            'category_id' => ['nullable', Rule::exists('account_categories', 'id')->where('type', $kind)],
            'amount'     => 'required|numeric|min:0.01|max:99999999',
            'txn_date'   => 'required|date',
            'note'       => 'nullable|string|max:255',
        ], [
            'account_id.required' => 'Please choose the account.',
            'account_id.in'       => 'Please choose a valid account.',
            'category_id.exists'  => 'Please choose a valid category.',
            'amount.required'     => 'Please enter the amount.',
            'amount.min'          => 'The amount must be greater than zero.',
            'txn_date.required'   => 'Please choose the date.',
        ]);
    }

    protected function entryStore(Request $request, string $kind)
    {
        $k    = $this->kinds()[$kind];
        $data = $this->entryData($request, $kind);

        AccountTransaction::create([
            'account_id'  => $data['account_id'],
            'direction'   => $k['dir'],
            'amount'      => $data['amount'],
            'txn_date'    => $data['txn_date'],
            'note'        => isset($data['note']) ? (trim($data['note']) ?: null) : null,
            'category_id' => $data['category_id'] ?? null,
            'source'      => $kind,
        ]);

        return redirect()->route('admin.accounting.' . $kind . '.index')->with('success', $k['label'] . ' added.');
    }

    /** Entries made on this page's module only. Entries that came from a customer payment are changed on the Payments page. */
    private function editableEntry(string $kind, $id): AccountTransaction
    {
        return AccountTransaction::where('source', $kind)->whereNull('payment_id')->findOrFail($id);
    }

    protected function entryEdit(string $kind, $id)
    {
        return $this->entryCreate($kind, $this->editableEntry($kind, $id));
    }

    protected function entryUpdate(Request $request, string $kind, $id)
    {
        $k     = $this->kinds()[$kind];
        $entry = $this->editableEntry($kind, $id);
        $data  = $this->entryData($request, $kind);

        $entry->update([
            'account_id'  => $data['account_id'],
            'amount'      => $data['amount'],
            'txn_date'    => $data['txn_date'],
            'note'        => isset($data['note']) ? (trim($data['note']) ?: null) : null,
            'category_id' => $data['category_id'] ?? null,
        ]);

        return redirect()->route('admin.accounting.' . $kind . '.index')->with('success', $k['label'] . ' updated.');
    }

    protected function entryDestroy(string $kind, $id)
    {
        // Only entries made on this page's module can be deleted here
        AccountTransaction::where('source', $kind)->findOrFail($id)->delete();

        return redirect()->route('admin.accounting.' . $kind . '.index')->with('success', 'Entry deleted.');
    }
}
