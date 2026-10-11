<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Admin\Accounting\Concerns\HandlesLedger;
use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\Request;

class BankAccountController extends Controller
{
    use HandlesLedger;

    public function index(Request $request)
    {
        $banks = Account::where('type', 'bank')->orderBy('name')->orderBy('id')->get();
        $rows  = $banks->map(fn ($b) => ['account' => $b, 'balance' => $b->totals()['balance']]);

        $editing = $request->filled('edit') ? $banks->firstWhere('id', (int) $request->query('edit')) : null;

        return view('admin.accounting.accounts.banks', [
            'rows'    => $rows,
            'total'   => (float) $rows->sum('balance'),
            'editing' => $editing,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'            => 'required|string|max:60',
            'account_no'      => 'nullable|string|max:40',
            'opening_balance' => 'nullable|numeric|min:0|max:99999999',
        ], [
            'name.required' => 'Please enter the bank name.',
        ]);

        Account::create([
            'type'            => 'bank',
            'name'            => trim($data['name']),
            'account_no'      => trim($data['account_no'] ?? '') ?: null,
            'opening_balance' => $data['opening_balance'] ?? 0,
        ]);

        return redirect()->route('admin.accounting.banks.index')->with('success', 'Bank account added.');
    }

    public function update(Request $request, $bank)
    {
        $account = $this->bank($bank);

        $data = $request->validate([
            'name'       => 'required|string|max:60',
            'account_no' => 'nullable|string|max:40',
        ], [
            'name.required' => 'Please enter the bank name.',
        ]);

        $account->update([
            'name'       => trim($data['name']),
            'account_no' => trim($data['account_no'] ?? '') ?: null,
        ]);

        return redirect()->route('admin.accounting.banks.index')->with('success', 'Bank account updated.');
    }

    public function destroy($bank)
    {
        $account = $this->bank($bank);

        if ($account->transactions()->exists()) {
            return back()->with('error', 'This account has entries. Delete its entries first.');
        }

        $account->delete();

        return redirect()->route('admin.accounting.banks.index')->with('success', 'Bank account deleted.');
    }

    public function show(Request $request, $bank)
    {
        $account = $this->bank($bank);

        return view('admin.accounting.accounts.show', $this->ledgerData($request, $account) + [
            'title'     => $account->name . ' — Bank',
            'heading'   => '🏦 ' . $account->name,
            'subtitle'  => $account->account_no ? 'A/C: ' . $account->account_no : null,
            'active'    => 'bank',
            'urlBase'   => route('admin.accounting.banks.show', $account->id),
            'backUrl'   => route('admin.accounting.banks.index'),
            'backLabel' => 'All bank accounts',
        ]);
    }

    public function storeEntry(Request $request, $bank)
    {
        $this->addEntry($request, $this->bank($bank));

        return redirect()->route('admin.accounting.banks.show', $bank)->with('success', 'Entry added.');
    }

    public function destroyEntry($bank, $entry)
    {
        $error = $this->removeEntry($this->bank($bank), $entry);

        if ($error) {
            return back()->with('error', $error);
        }

        return redirect()->route('admin.accounting.banks.show', $bank)->with('success', 'Entry deleted.');
    }

    public function updateOpening(Request $request, $bank)
    {
        $this->saveOpening($request, $this->bank($bank));

        return redirect()->route('admin.accounting.banks.show', $bank)->with('success', 'Opening balance saved.');
    }

    private function bank($id): Account
    {
        return Account::where('type', 'bank')->findOrFail($id);
    }
}
