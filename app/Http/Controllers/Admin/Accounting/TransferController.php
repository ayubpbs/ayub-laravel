<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Admin\Accounting\Concerns\HandlesEntries;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\AccountTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class TransferController extends Controller
{
    use HandlesEntries; // only used for accountChoices() (Cash, banks, bKash, Nagad)

    public function index(Request $request)
    {
        $accounts = $this->accountChoices();
        $ids      = array_column($accounts, 'id');

        $date = fn ($v) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $v) ? (string) $v : '';
        $acc  = (int) $request->query('account');

        $f = [
            'from'    => $date($request->query('from')),
            'to'      => $date($request->query('to')),
            'account' => in_array($acc, $ids, true) ? $acc : '',
        ];
        $filtered = $f['from'] !== '' || $f['to'] !== '' || $f['account'] !== '';

        $apply = function ($q) use ($f) {
            if ($f['from'] !== '') $q->whereDate('txn_date', '>=', $f['from']);
            if ($f['to'] !== '')   $q->whereDate('txn_date', '<=', $f['to']);
            if ($f['account'] !== '') {
                // transfers where this account is on either side
                $q->whereIn('transfer_ref', AccountTransaction::query()
                    ->where('source', 'transfer')->where('account_id', $f['account'])->select('transfer_ref'));
            }

            return $q;
        };

        // One transfer = one "out" row (the source account); the matching "in" row is looked up by its code
        $base = fn () => AccountTransaction::query()->where('source', 'transfer')->where('direction', 'out');

        $transfers = $apply($base())->with('account')
            ->orderByDesc('txn_date')->orderByDesc('id')
            ->paginate(20)->withQueryString();

        $incoming = AccountTransaction::query()
            ->where('source', 'transfer')->where('direction', 'in')
            ->whereIn('transfer_ref', $transfers->pluck('transfer_ref')->all())
            ->with('account')->get()->keyBy('transfer_ref');

        return view('admin.accounting.transfers.index', [
            'accounts'   => $accounts,
            'transfers'  => $transfers,
            'incoming'   => $incoming,
            'f'          => $f,
            'filtered'   => $filtered,
            'listTotal'  => (float) $apply($base())->sum('amount'),
            'monthTotal' => (float) $base()->whereDate('txn_date', '>=', now()->startOfMonth()->toDateString())->sum('amount'),
            'allTotal'   => (float) $base()->sum('amount'),
        ]);
    }

    /** Validated form data (the same rules for adding and editing a transfer) */
    private function validated(Request $request): array
    {
        $ids = array_column($this->accountChoices(), 'id');

        return $request->validate([
            'from_account_id' => ['required', Rule::in($ids)],
            'to_account_id'   => ['required', Rule::in($ids), 'different:from_account_id'],
            'amount'          => 'required|numeric|min:0.01|max:99999999',
            'txn_date'        => 'required|date',
            'note'            => 'nullable|string|max:150',
        ], [
            'from_account_id.required' => 'Please choose the account the money leaves.',
            'from_account_id.in'       => 'Please choose a valid "from" account.',
            'to_account_id.required'   => 'Please choose the account the money goes to.',
            'to_account_id.in'         => 'Please choose a valid "to" account.',
            'to_account_id.different'  => 'The two accounts must be different.',
            'amount.required'          => 'Please enter the amount.',
            'amount.min'               => 'The amount must be greater than zero.',
            'txn_date.required'        => 'Please choose the date.',
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        try {
            DB::transaction(function () use ($data) {
                // Lock the source account so two transfers at the same moment cannot spend the same money
                $from = Account::whereKey($data['from_account_id'])->lockForUpdate()->firstOrFail();
                $to   = Account::findOrFail($data['to_account_id']);

                $amount  = round((float) $data['amount'], 2);
                $balance = round($from->totals()['balance'], 2);

                if ($amount > $balance) {
                    throw ValidationException::withMessages([
                        'amount' => 'Not enough balance in ' . $from->name . '. Available: ৳ ' . number_format($balance, 2)
                            . '. Add money or set its opening balance first.',
                    ]);
                }

                $ref  = (string) Str::uuid();
                $note = trim($data['note'] ?? '');

                AccountTransaction::create([
                    'account_id'   => $from->id,
                    'direction'    => 'out',
                    'amount'       => $amount,
                    'txn_date'     => $data['txn_date'],
                    'note'         => 'Transfer to ' . $to->name . ($note !== '' ? ' — ' . $note : ''),
                    'source'       => 'transfer',
                    'transfer_ref' => $ref,
                ]);

                AccountTransaction::create([
                    'account_id'   => $to->id,
                    'direction'    => 'in',
                    'amount'       => $amount,
                    'txn_date'     => $data['txn_date'],
                    'note'         => 'Transfer from ' . $from->name . ($note !== '' ? ' — ' . $note : ''),
                    'source'       => 'transfer',
                    'transfer_ref' => $ref,
                ]);
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', 'Could not save the transfer. Nothing was changed. Please try again.');
        }

        return redirect()->route('admin.accounting.transfers.index')->with('success', 'Transfer saved.');
    }

    /** The two rows of one transfer: [out row, in row] */
    private function transferRows(string $ref): array
    {
        $rows = AccountTransaction::where('source', 'transfer')->where('transfer_ref', $ref)->lockForUpdate()->get();
        $out  = $rows->firstWhere('direction', 'out');
        $in   = $rows->firstWhere('direction', 'in');
        abort_if(!$out || !$in, 404);

        return [$out, $in];
    }

    public function edit($ref)
    {
        [$out, $in] = $this->transferRows($ref);

        $note = trim((string) \Illuminate\Support\Str::after((string) $out->note, ' — '));
        $note = $note === trim((string) $out->note) ? '' : $note;

        return view('admin.accounting.transfers.edit', [
            'accounts' => $this->accountChoices(),
            'ref'      => $ref,
            'transfer' => $out,
            'toId'     => $in->account_id,
            'note'     => $note,
        ]);
    }

    public function update(Request $request, $ref)
    {
        $data = $this->validated($request);

        try {
            DB::transaction(function () use ($data, $ref) {
                [$out, $in] = $this->transferRows($ref);

                $from = Account::whereKey($data['from_account_id'])->lockForUpdate()->firstOrFail();
                $to   = Account::findOrFail($data['to_account_id']);

                $amount = round((float) $data['amount'], 2);

                // balance of the source account as it would be WITHOUT this transfer
                $balance = $from->totals()['balance'];
                if ((int) $out->account_id === (int) $from->id) $balance += (float) $out->amount;
                if ((int) $in->account_id === (int) $from->id) $balance -= (float) $in->amount;
                $balance = round($balance, 2);

                if ($amount > $balance) {
                    throw ValidationException::withMessages([
                        'amount' => 'Not enough balance in ' . $from->name . '. Available: ৳ ' . number_format($balance, 2) . '.',
                    ]);
                }

                $note = trim($data['note'] ?? '');

                $out->update([
                    'account_id' => $from->id,
                    'amount'     => $amount,
                    'txn_date'   => $data['txn_date'],
                    'note'       => 'Transfer to ' . $to->name . ($note !== '' ? ' — ' . $note : ''),
                ]);
                $in->update([
                    'account_id' => $to->id,
                    'amount'     => $amount,
                    'txn_date'   => $data['txn_date'],
                    'note'       => 'Transfer from ' . $from->name . ($note !== '' ? ' — ' . $note : ''),
                ]);
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                throw $e;
            }
            report($e);

            return back()->withInput()->with('error', 'Could not save the changes. Nothing was changed. Please try again.');
        }

        return redirect()->route('admin.accounting.transfers.index')->with('success', 'Transfer updated. Both account balances were corrected.');
    }

    public function destroy($ref)
    {
        try {
            DB::transaction(function () use ($ref) {
                $rows = AccountTransaction::where('source', 'transfer')->where('transfer_ref', $ref)->lockForUpdate()->get();
                abort_if($rows->isEmpty(), 404);

                // both sides are removed together, so the two balances always stay in step
                AccountTransaction::where('source', 'transfer')->where('transfer_ref', $ref)->delete();
            });
        } catch (Throwable $e) {
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                throw $e;
            }
            report($e);

            return back()->with('error', 'Could not delete the transfer. Nothing was changed.');
        }

        return redirect()->route('admin.accounting.transfers.index')->with('success', 'Transfer deleted. Both account balances were corrected.');
    }
}
