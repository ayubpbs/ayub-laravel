<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Admin\Accounting\Concerns\HandlesEntries;
use App\Http\Controllers\Controller;
use App\Models\AccountTransaction;
use App\Models\Holding;
use App\Models\HoldingPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Savings certificates, FDR, loans given / taken and cash receivables */
class HoldingController extends Controller
{
    use HandlesEntries; // only used for accountChoices() (Cash, banks, bKash, Nagad)

    public function index(Request $request)
    {
        $kinds  = Holding::kinds();
        $kind   = isset($kinds[(string) $request->query('kind')]) ? (string) $request->query('kind') : '';
        $status = in_array($request->query('status'), ['open', 'closed', 'all'], true) ? $request->query('status') : 'open';

        $q = Holding::query()->withSum('payments as settled_sum', 'amount');
        if ($kind !== '')       $q->where('kind', $kind);
        if ($status === 'open')   $q->open();
        if ($status === 'closed') $q->closed();

        $items = $q->orderByRaw('CASE WHEN maturity_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('maturity_date')->orderByDesc('id')
            ->paginate(20)->withQueryString();

        return view('admin.accounting.savings.index', [
            'kinds'   => $kinds,
            'kind'    => $kind,
            'status'  => $status,
            'items'   => $items,
            'summary' => Holding::summary(),
            'counts'  => Holding::open()->selectRaw('kind, COUNT(*) as c')->groupBy('kind')->pluck('c', 'kind'),
        ]);
    }

    public function create(Request $request)
    {
        $kind = isset(Holding::kinds()[(string) $request->query('kind')]) ? (string) $request->query('kind') : 'sanchaypatra';

        return view('admin.accounting.savings.form', [
            'item'     => null,
            'kinds'    => Holding::kinds(),
            'kind'     => $kind,
            'accounts' => $this->accountChoices(),
            'locked'   => false,
        ]);
    }

    public function store(Request $request)
    {
        $kinds = Holding::kinds();
        $ids   = array_column($this->accountChoices(), 'id');

        $data = $request->validate($this->rules() + [
            'kind'       => ['required', Rule::in(array_keys($kinds))],
            'principal'  => 'required|numeric|min:0.01|max:99999999',
            'account_id' => ['nullable', Rule::in($ids)],
        ], $this->messages());

        $k = $kinds[$data['kind']];

        DB::transaction(function () use ($data, $k) {
            $item = Holding::create($this->fields($data) + ['kind' => $data['kind'], 'principal' => $data['principal']]);

            // Optional: also move the money in the chosen account (Loan taken = money in, the others = money out)
            if ($k['moves_at_start'] && !empty($data['account_id'])) {
                $tx = AccountTransaction::create([
                    'account_id' => $data['account_id'],
                    'direction'  => $k['side'] === 'asset' ? 'out' : 'in',
                    'amount'     => $data['principal'],
                    'txn_date'   => $data['start_date'],
                    'note'       => $k['label'] . ': ' . $item->title,
                    'source'     => 'holding',
                ]);
                $item->update(['account_transaction_id' => $tx->id]);
            }
        });

        return redirect()->route('admin.accounting.savings.index', ['kind' => $data['kind']])
            ->with('success', $k['label'] . ' added.');
    }

    public function show($id)
    {
        $item = Holding::findOrFail($id);

        return view('admin.accounting.savings.show', [
            'item'        => $item,
            'k'           => $item->meta(),
            'settled'     => $item->settled(),
            'outstanding' => $item->outstanding(),
            'payments'    => $item->payments()->orderByDesc('paid_date')->orderByDesc('id')->get(),
            'accounts'    => $this->accountChoices(),
        ]);
    }

    public function edit($id)
    {
        $item = Holding::findOrFail($id);

        return view('admin.accounting.savings.form', [
            'item'     => $item,
            'kinds'    => Holding::kinds(),
            'kind'     => $item->kind,
            'accounts' => [],
            // The amount is fixed once an account entry was made from it (so the account balance never goes out of step)
            'locked'   => $item->account_transaction_id !== null,
        ]);
    }

    public function update(Request $request, $id)
    {
        $item   = Holding::findOrFail($id);
        $locked = $item->account_transaction_id !== null;
        $min    = max(0.01, $item->settled());

        $rules = $this->rules();
        if (!$locked) {
            $rules['principal'] = 'required|numeric|min:' . number_format($min, 2, '.', '') . '|max:99999999';
        }

        $data = $request->validate($rules, $this->messages() + [
            'principal.min' => 'The amount cannot be less than what is already received / repaid (৳ ' . number_format($item->settled(), 2) . ').',
        ]);

        $item->update($this->fields($data) + ($locked ? [] : ['principal' => $data['principal']]));

        return redirect()->route('admin.accounting.savings.show', $item->id)->with('success', 'Saved.');
    }

    public function destroy($id)
    {
        $item = Holding::findOrFail($id);

        DB::transaction(function () use ($item) {
            $txIds = $item->payments()->whereNotNull('account_transaction_id')->pluck('account_transaction_id')->all();
            if ($item->account_transaction_id) {
                $txIds[] = $item->account_transaction_id;
            }
            if ($txIds) {
                AccountTransaction::whereIn('id', $txIds)->where('source', 'holding')->delete();
            }
            $item->delete(); // its payments go with it
        });

        return redirect()->route('admin.accounting.savings.index')
            ->with('success', 'Deleted. Any account entries made from it were removed too.');
    }

    /** Money received back (asset) or repaid (loan taken) */
    public function settle(Request $request, $id)
    {
        $item = Holding::findOrFail($id);
        $out  = $item->outstanding();

        if ($out <= 0) {
            return back()->with('error', 'Nothing is outstanding on this item.');
        }

        $ids = array_column($this->accountChoices(), 'id');

        $data = $request->validate([
            'account_id' => ['required', Rule::in($ids)],
            'amount'     => 'required|numeric|min:0.01|max:' . number_format($out, 2, '.', ''),
            'paid_date'  => 'required|date',
            'note'       => 'nullable|string|max:255',
        ], [
            'account_id.required' => 'Please choose the account the money went into / came out of.',
            'account_id.in'       => 'Please choose a valid account.',
            'amount.required'     => 'Please enter the amount.',
            'amount.min'          => 'The amount must be greater than zero.',
            'amount.max'          => 'The amount is more than what is outstanding (৳ ' . number_format($out, 2) . ').',
            'paid_date.required'  => 'Please choose the date.',
        ]);

        $k    = $item->meta();
        $note = isset($data['note']) ? (trim($data['note']) ?: null) : null;

        DB::transaction(function () use ($item, $data, $k, $note) {
            $tx = AccountTransaction::create([
                'account_id' => $data['account_id'],
                'direction'  => $k['side'] === 'asset' ? 'in' : 'out',
                'amount'     => $data['amount'],
                'txn_date'   => $data['paid_date'],
                'note'       => ($k['side'] === 'asset' ? 'Received: ' : 'Repaid: ') . $item->title . ($note ? ' — ' . $note : ''),
                'source'     => 'holding',
            ]);

            HoldingPayment::create([
                'holding_id'             => $item->id,
                'amount'                 => $data['amount'],
                'paid_date'              => $data['paid_date'],
                'note'                   => $note,
                'account_transaction_id' => $tx->id,
            ]);
        });

        return redirect()->route('admin.accounting.savings.show', $item->id)
            ->with('success', $k['side'] === 'asset' ? 'Money received — saved in the chosen account.' : 'Repayment saved — taken from the chosen account.');
    }

    public function unsettle($id, $payment)
    {
        $item = Holding::findOrFail($id);
        $pay  = $item->payments()->findOrFail($payment);

        DB::transaction(function () use ($pay) {
            if ($pay->account_transaction_id) {
                AccountTransaction::where('id', $pay->account_transaction_id)->where('source', 'holding')->delete();
            }
            $pay->delete();
        });

        return back()->with('success', 'Entry deleted and the account balance corrected.');
    }

    // ---------------------------------------------------------------

    private function rules(): array
    {
        return [
            'title'         => 'required|string|max:80',
            'party'         => 'nullable|string|max:80',
            'reference_no'  => 'nullable|string|max:40',
            'interest_rate' => 'nullable|numeric|min:0|max:100',
            'start_date'    => 'required|date',
            'maturity_date' => 'nullable|date|after_or_equal:start_date',
            'note'          => 'nullable|string|max:255',
        ];
    }

    private function messages(): array
    {
        return [
            'title.required'                 => 'Please enter a name / title.',
            'principal.required'             => 'Please enter the amount.',
            'principal.min'                  => 'The amount must be greater than zero.',
            'start_date.required'            => 'Please choose the date.',
            'maturity_date.after_or_equal'   => 'The due / maturity date cannot be before the start date.',
            'kind.required'                  => 'Please choose the type.',
            'account_id.in'                  => 'Please choose a valid account.',
        ];
    }

    private function fields(array $d): array
    {
        $t = fn ($v) => ($v = trim((string) $v)) === '' ? null : $v;

        return [
            'title'         => trim($d['title']),
            'party'         => $t($d['party'] ?? ''),
            'reference_no'  => $t($d['reference_no'] ?? ''),
            'interest_rate' => ($d['interest_rate'] ?? '') === '' || !isset($d['interest_rate']) ? null : $d['interest_rate'],
            'start_date'    => $d['start_date'],
            'maturity_date' => $d['maturity_date'] ?? null,
            'note'          => $t($d['note'] ?? ''),
        ];
    }
}
