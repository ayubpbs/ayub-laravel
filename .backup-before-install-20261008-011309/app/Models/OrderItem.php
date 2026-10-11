<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = ['order_id', 'product_id', 'title', 'price', 'quantity', 'line_total'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
