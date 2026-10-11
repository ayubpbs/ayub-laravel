<?php

namespace App\Http\Controllers\Admin\Accounting;

use App\Http\Controllers\Admin\Accounting\Concerns\HandlesEntries;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Setting;
use App\Support\PaymentLedger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingController extends Controller
{
    use HandlesEntries; // accountChoices()

    public function edit()
    {
        $ready = PaymentLedger::ready();
        $start = $ready ? PaymentLedger::startsAt() : null;

        return view('admin.accounting.settings', [
            'ready'         => $ready,
            'accounts'      => $this->accountChoices(),
            'defaultAccount' => (int) Setting::get('acct_default_account', 0),
            'courierCharge' => (int) Setting::get('acct_courier_charge', 0),
            'startDate'     => $start ? substr($start, 0, 10) : '',
            // customer payments recorded before the start date: they are in no account (see "Payment Receive")
            'olderCount'    => $ready ? Payment::doesntHave('accountEntry')->where('created_at', '<', $start)->count() : 0,
            'waitingCount'  => $ready ? PaymentLedger::pending()->count() : 0,
        ]);
    }

    public function update(Request $request)
    {
        $ids = array_column($this->accountChoices(), 'id');

        $data = $request->validate([
            'default_account' => ['nullable', Rule::in($ids)],
            'courier_charge'  => 'nullable|integer|min:0|max:100000',
            'start_date'      => PaymentLedger::ready() ? 'required|date|before_or_equal:today' : 'nullable',
        ], [
            'default_account.in'          => 'Please choose a valid account.',
            'courier_charge.integer'      => 'The courier charge must be a whole number of taka.',
            'start_date.required'         => 'Please choose the date.',
            'start_date.before_or_equal'  => 'The date cannot be in the future.',
        ]);

        $values = [
            'acct_default_account' => $data['default_account'] ?? '',
            'acct_courier_charge'  => (int) ($data['courier_charge'] ?? 0),
        ];
        if (PaymentLedger::ready() && !empty($data['start_date'])) {
            $values['acct_link_start'] = $data['start_date'] . ' 00:00:00';
        }

        Setting::setMany($values);

        return redirect()->route('admin.accounting.settings.edit')->with('success', 'Accounting settings saved.');
    }
}
