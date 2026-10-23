<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'title', 'price', 'quantity', 'line_total',
        // Buying price of one unit when it was sold (a snapshot; later cost changes do not touch it). Used by the Profit / Loss report.
        'cost_price',
        // Phase 6: the warranty that applied when the goods were sold (a snapshot; later product changes do not touch it)
        'warranty_type', 'warranty_days', 'warranty_terms', 'warranty_source',
    ];

    private static ?bool $costColumn = null;

    /** False until the cost_price migration has been run, so ordering keeps working before that. */
    public static function hasCostColumn(): bool
    {
        if (self::$costColumn === null) {
            try {
                self::$costColumn = Schema::hasColumn('order_items', 'cost_price');
            } catch (\Throwable $e) {
                self::$costColumn = false;
            }
        }

        return self::$costColumn;
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
