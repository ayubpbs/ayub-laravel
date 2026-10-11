<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountTransaction extends Model
{
    protected $fillable = ['account_id', 'direction', 'amount', 'txn_date', 'note', 'category_id', 'source', 'transfer_ref', 'payment_id'];

    protected $casts = ['txn_date' => 'date'];

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function category()
    {
        return $this->belongsTo(AccountCategory::class, 'category_id');
    }

    public function isIn(): bool
    {
        return $this->direction === 'in';
    }
}
