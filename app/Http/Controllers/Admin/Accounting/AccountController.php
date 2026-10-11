<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Admin\Accounting\Concerns\HandlesLedger;
use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    use HandlesLedger;

    public function show(Request $request, $type)
    {
        $account = $this->account($type);
        $info    = Account::types()[$type];

        return view('admin.accounting.accounts.show', $this->ledgerData($request, $account) + [
            'title'   => $info[0] . ' Account',
            'heading' => $info[1] . ' ' . $info[0] . ' Account',
            'active'  => $type,
            'urlBase' => route('admin.accounting.accounts.show', $type),
        ]);
    }

    public function store(Request $request, $type)
    {
        $this->addEntry($request, $this->account($type));

        return redirect()->route('admin.accounting.accounts.show', $type)->with('success', 'Entry added.');
    }

    public function destroy($type, $id)
    {
        $error = $this->removeEntry($this->account($type), $id);

        if ($error) {
            return back()->with('error', $error);
        }

        return redirect()->route('admin.accounting.accounts.show', $type)->with('success', 'Entry deleted.');
    }

    public function updateOpening(Request $request, $type)
    {
        $this->saveOpening($request, $this->account($type));

        return redirect()->route('admin.accounting.accounts.show', $type)->with('success', 'Opening balance saved.');
    }

    private function account(string $type): Account
    {
        abort_unless(isset(Account::types()[$type]), 404);

        return Account::forType($type);
    }
}
