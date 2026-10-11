<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'name', 'phone', 'address', 'note',
        'subtotal', 'delivery_charge', 'total', 'status',
    ];

    public static function statuses(): array
    {
        return [
            'pending'   => 'New order',
            'confirmed' => 'Confirmed',
            'shipped'   => 'Shipped',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled',
        ];
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusLabel(): string
    {
        return self::statuses()[$this->status] ?? $this->status;
    }
}
