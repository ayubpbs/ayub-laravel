<?php

namespace App\Support;

use App\Models\Account;
use App\Models\AccountCategory;
use App\Models\AccountTransaction;
use App\Models\Payment;
use App\Models\Setting;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Connects customer payments / refunds (payments table) with the money accounts (account_transactions).
 *
 *  - cash / bKash / Nagad (and Bank when there is only one bank) go into their account by themselves.
 *  - Cash on delivery is NOT in any account yet: the money is with the courier. It stays in the
 *    "waiting" list (Accounting → Customers → Payment Receive) until the courier pays and someone
 *    puts it into an account.
 *  - Rocket / Other / Bank (with several banks) also wait until an account is chosen.
 */
class PaymentLedger
{
    private static ?bool $ready = null;

    /** Migration has been run and a start time exists */
    public static function ready(): bool
    {
        if (self::$ready === null) {
            try {
                self::$ready = Schema::hasColumn('account_transactions', 'payment_id') && Setting::get('acct_link_start') !== null;
            } catch (Throwable $e) {
                self::$ready = false;
            }
        }

        return self::$ready;
    }

    public static function startsAt(): ?string
    {
        return Setting::get('acct_link_start');
    }

    /** The account this payment goes into on its own, or null when someone has to choose */
    public static function autoAccount(Payment $p): ?Account
    {
        switch ($p->method) {
            case 'cash':
            case 'bkash':
            case 'nagad':
                return Account::forType($p->method);
            case 'bank':
                $banks = Account::where('type', 'bank')->limit(2)->get();

                return $banks->count() === 1 ? $banks->first() : null;
        }

        return null;
    }

    /** Payments and refunds that are not in any account yet (only those recorded after the link was switched on) */
    public static function pending()
    {
        if (!self::ready()) {
            return Payment::query()->whereRaw('1 = 0');
        }

        return Payment::query()->with('order')->doesntHave('accountEntry')
            ->where('created_at', '>=', self::startsAt());
    }

    /** Puts one payment into an account (once only) */
    public static function post(Payment $p, Account $account, ?string $date = null): AccountTransaction
    {
        $refund = $p->isRefund();
        $p->loadMissing('order');

        $note = ($refund ? 'Refund' : 'Payment') . ' · Order #' . $p->order_id
            . ($p->order && $p->order->name ? ' · ' . $p->order->name : '')
            . ' · ' . $p->methodLabel()
            . ($p->trx_id ? ' · ' . $p->trx_id : '');

        return AccountTransaction::firstOrCreate(
            ['payment_id' => $p->id],
            [
                'account_id'  => $account->id,
                'direction'   => $refund ? 'out' : 'in',
                'amount'      => $p->amount,
                'txn_date'    => $date ?: $p->paid_at->toDateString(),
                'note'        => mb_substr($note, 0, 255),
                'category_id' => AccountCategory::firstOrCreate(
                    ['type' => $refund ? 'expense' : 'income', 'name' => $refund ? 'Customer refund' : 'Product sales']
                )->id,
                'source'      => $refund ? 'expense' : 'income',
            ]
        );
    }

    /** Called when a payment is created: books it straight away when the account is obvious */
    public static function autoPost(Payment $p): void
    {
        if (!self::ready()) {
            return;
        }

        try {
            if ($account = self::autoAccount($p)) {
                self::post($p, $account);
            }
        } catch (Throwable $e) {
            report($e); // never block recording a payment because of the accounts
        }
    }

    /** Called when a payment is deleted: its account entry goes too */
    public static function forget(Payment $p): void
    {
        try {
            if (Schema::hasColumn('account_transactions', 'payment_id')) {
                AccountTransaction::where('payment_id', $p->id)->delete();
            }
        } catch (Throwable $e) {
            report($e);
        }
    }
}
