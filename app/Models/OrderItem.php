<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'title', 'price', 'quantity', 'line_total',
        // Phase 6: the warranty that applied when the goods were sold (a snapshot; later product changes do not touch it)
        'warranty_type', 'warranty_days', 'warranty_terms', 'warranty_source',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
