<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    protected $fillable = [
        'name', 'phone', 'district', 'upazila', 'city', 'address', 'note',
        'subtotal', 'coupon_code', 'discount', 'delivery_charge', 'total', 'status',
        'payment_method', 'bkash_amount', 'bkash_trx_id', 'bkash_payment_id',
        'courier', 'consignment_id', 'tracking_code', 'courier_status',
        'courier_sent_at', 'courier_checked_at', 'return_requested_at',
        'courier_sent_by', 'courier_sent_by_name',
    ];

    protected $casts = [
        'courier_sent_at'     => 'datetime',
        'courier_checked_at'  => 'datetime',
        'return_requested_at' => 'datetime',
    ];

    public static function statuses(): array
    {
        return [
            'pending'      => 'Pending',
            'confirmed'    => 'Confirmed',
            'courier_sent' => 'Courier Sent',
            'delivered'    => 'Delivered',
            'cancelled'    => 'Cancelled',
            'returned'     => 'Returned',
        ];
    }

    /** Final statuses: once reached, the status can no longer be changed (stock was already restored). */
    public static function lockedStatuses(): array
    {
        return ['cancelled', 'returned'];
    }

    public function isLocked(): bool
    {
        return in_array($this->status, self::lockedStatuses(), true);
    }

    // ----- Payment method (chosen at checkout) -----

    public function isBkash(): bool
    {
        return $this->payment_method === 'bkash';
    }

    public function paymentMethodLabel(): string
    {
        return $this->isBkash() ? 'bKash' : 'Cash on delivery';
    }

    /** True once the admin has verified the bKash payment (a payment row exists for it) */
    public function bkashVerified(): bool
    {
        if (!$this->isBkash() || !$this->bkash_payment_id) {
            return false;
        }
        if ($this->relationLoaded('payments')) {
            return $this->payments->contains('id', $this->bkash_payment_id);
        }

        return $this->payments()->where('id', $this->bkash_payment_id)->exists();
    }

    // ----- Payments -----

    // SQL for "money received minus refunded" on an order (works on MySQL and SQLite)
    const NET_PAID_SQL = "COALESCE((SELECT SUM(CASE WHEN p.type = 'payment' THEN p.amount ELSE -p.amount END) FROM payments p WHERE p.order_id = orders.id), 0)";

    public static function paymentStatuses(): array
    {
        return [
            'unpaid'     => 'Unpaid',
            'partial'    => 'Partly paid',
            'paid'       => 'Paid',
            'overpaid'   => 'Overpaid',
            'refund_due' => 'Refund due',
            'void'       => '—',
        ];
    }

    public static function paymentBadge(string $status): string
    {
        return [
            'unpaid'     => 'st-pending',
            'partial'    => 'st-confirmed',
            'paid'       => 'st-delivered',
            'overpaid'   => 'st-courier_sent',
            'refund_due' => 'st-returned',
            'void'       => 'st-cancelled',
        ][$status] ?? 'st-pending';
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    /** Adds paid_in / paid_out columns so lists can show payment status without extra queries */
    public function scopeWithPaid($query)
    {
        return $query
            ->withSum(['payments as paid_in'  => fn ($q) => $q->where('type', 'payment')], 'amount')
            ->withSum(['payments as paid_out' => fn ($q) => $q->where('type', 'refund')], 'amount');
    }

    public function scopeOwing($query)       { return $query->whereRaw('orders.total > ' . self::NET_PAID_SQL); }
    public function scopeSettled($query)     { return $query->whereRaw('orders.total <= ' . self::NET_PAID_SQL); }
    public function scopeNothingPaid($query) { return $query->whereRaw(self::NET_PAID_SQL . ' <= 0'); }
    public function scopePartlyPaid($query)  { return $query->whereRaw(self::NET_PAID_SQL . ' > 0 AND orders.total > ' . self::NET_PAID_SQL); }

    /** Money received minus money refunded */
    public function netPaid(): int
    {
        if (array_key_exists('paid_in', $this->attributes)) {
            return (int) $this->attributes['paid_in'] - (int) ($this->attributes['paid_out'] ?? 0);
        }
        if ($this->relationLoaded('payments')) {
            return (int) $this->payments->where('type', 'payment')->sum('amount') - (int) $this->payments->where('type', 'refund')->sum('amount');
        }

        return (int) $this->payments()->where('type', 'payment')->sum('amount') - (int) $this->payments()->where('type', 'refund')->sum('amount');
    }

    /** What the customer still has to pay (0 for cancelled / returned orders) */
    public function dueAmount(): int
    {
        return $this->isLocked() ? 0 : max(0, (int) $this->total - $this->netPaid());
    }

    public function paymentStatus(): string
    {
        $net = $this->netPaid();

        if ($this->isLocked()) {
            return $net > 0 ? 'refund_due' : 'void';
        }
        if ($net <= 0)              return 'unpaid';
        if ($net < $this->total)    return 'partial';
        if ($net > $this->total)    return 'overpaid';

        return 'paid';
    }

    public function paymentLabel(): string
    {
        return self::paymentStatuses()[$this->paymentStatus()];
    }

    /**
     * Changes the status. Cancelled and returned orders put their products back into stock.
     * Callers must make sure the order is not already locked.
     */
    public function changeStatus(string $new): void
    {
        DB::transaction(function () use ($new) {
            if (in_array($new, self::lockedStatuses(), true)) {
                foreach ($this->items as $item) {
                    if ($item->product_id) {
                        Product::where('id', $item->product_id)->increment('stock_quantity', $item->quantity);
                    }
                }
            }
            $this->update(['status' => $new]);

            \App\Support\Notify::orderStatus($this, $new);

            // Orders that are confirmed or further along get an invoice automatically
            if (in_array($new, Invoice::eligibleStatuses(), true)) {
                Invoice::ensureFor($this);
            }
        });
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
