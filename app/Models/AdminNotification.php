<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Throwable;

class AdminNotification extends Model
{
    protected $fillable = ['type', 'title', 'message', 'order_id', 'product_id', 'url', 'read_at'];

    protected $casts = ['read_at' => 'datetime'];

    /** type => [label, icon, css class] */
    public static function types(): array
    {
        return [
            'new_order'       => ['New Order',       '🛒', 'nt-new'],
            'order_confirmed' => ['Order Confirmed', '✅', 'nt-confirmed'],
            'courier_sent'    => ['Courier Sent',    '🚚', 'nt-courier'],
            'delivered'       => ['Delivered',       '📦', 'nt-delivered'],
            'low_stock'       => ['Low Stock',       '⚠️', 'nt-stock'],
            'return'          => ['Return',          '↩️', 'nt-return'],
            'payment'         => ['Payment',         '💳', 'nt-payment'],
            'warranty_claim'  => ['Warranty Claim',  '🛡️', 'nt-return'],
        ];
    }

    public function meta(): array
    {
        return self::types()[$this->type] ?? [ucfirst(str_replace('_', ' ', $this->type)), '🔔', 'nt-new'];
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    /** Never breaks a page if the migration has not been run yet */
    public static function unreadCount(): int
    {
        try {
            return (int) static::whereNull('read_at')->count();
        } catch (Throwable $e) {
            return 0;
        }
    }
}
