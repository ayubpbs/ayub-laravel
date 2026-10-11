<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

class Invoice extends Model
{
    protected $fillable = ['order_id', 'issued_at', 'note'];

    protected $casts = ['issued_at' => 'datetime'];

    /** Orders that get an invoice automatically */
    public static function eligibleStatuses(): array
    {
        return ['confirmed', 'courier_sent', 'delivered'];
    }

    /** INV-00012 */
    public function number(): string
    {
        return 'INV-' . str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    /** Returns the order's invoice, creating it if it does not exist yet (safe to call twice). */
    public static function ensureFor(Order $order): self
    {
        try {
            return self::firstOrCreate(['order_id' => $order->id], ['issued_at' => now()]);
        } catch (QueryException $e) {
            // Two requests created it at the same moment: the other one won, use that
            return self::where('order_id', $order->id)->firstOrFail();
        }
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
