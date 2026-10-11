<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = ['order_id', 'type', 'method', 'amount', 'trx_id', 'sender_number', 'note', 'paid_at'];

    protected $casts = ['paid_at' => 'datetime'];

    protected static function booted()
    {
        // Keep the money accounts in step with customer payments (see App\Support\PaymentLedger)
        static::created(fn (Payment $p) => \App\Support\PaymentLedger::autoPost($p));
        static::deleted(fn (Payment $p) => \App\Support\PaymentLedger::forget($p));
    }

    public static function methods(): array
    {
        return [
            'cod'    => 'Cash on delivery (courier)',
            'cash'   => 'Cash (at shop)',
            'bkash'  => 'bKash',
            'nagad'  => 'Nagad',
            'rocket' => 'Rocket',
            'bank'   => 'Bank transfer',
            'other'  => 'Other',
        ];
    }

    /** Methods where the customer sends money from a phone, so a transaction ID must be recorded */
    public static function mobileMethods(): array
    {
        return ['bkash', 'nagad', 'rocket'];
    }

    public function methodLabel(): string
    {
        return self::methods()[$this->method] ?? ucfirst($this->method);
    }

    public function isRefund(): bool
    {
        return $this->type === 'refund';
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /** The account entry this payment was booked as (none while the money is still with the courier) */
    public function accountEntry()
    {
        return $this->hasOne(AccountTransaction::class, 'payment_id');
    }
}
