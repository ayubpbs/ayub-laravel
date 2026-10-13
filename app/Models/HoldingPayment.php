<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One part-payment (money received back / money repaid) against a Holding */
class HoldingPayment extends Model
{
    protected $table = 'account_holding_payments';

    protected $fillable = ['holding_id', 'amount', 'paid_date', 'note', 'account_transaction_id'];

    protected $casts = ['paid_date' => 'date'];

    public function holding()
    {
        return $this->belongsTo(Holding::class, 'holding_id');
    }
}
