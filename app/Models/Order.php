<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Order extends Model
{
    protected $fillable = [
        'name', 'phone', 'district', 'upazila', 'city', 'address', 'note',
        'subtotal', 'coupon_code', 'discount', 'delivery_charge', 'total', 'status',
        'payment_method', 'bkash_amount', 'bkash_trx_id', 'bkash_payment_id',
        'courier', 'consignment_id', 'tracking_code', 'courier_status',
        'courier_sent_at', 'courier_checked_at', 'return_requested_at',
        'courier_sent_by', 'courier_sent_by_name',
        // Phase 4: orders created by an admin
        'created_by_id', 'created_by_name', 'source_order_id', 'client_token', 'address_id', 'delivered_at', 'order_type',
        // Phase 5: replacement / exchange orders point at their claim
        'service_request_id',
    ];

    protected $casts = [
        'courier_sent_at'     => 'datetime',
        'courier_checked_at'  => 'datetime',
        'return_requested_at' => 'datetime',
        'delivered_at'        => 'datetime',
    ];

    private static ?bool $adminColumns = null;

    /** False until the phase 4 migration has been run, so everything keeps working before that. */
    public static function hasAdminColumns(): bool
    {
        if (self::$adminColumns === null) {
            try {
                self::$adminColumns = Schema::hasColumn('orders', 'created_by_name') && Schema::hasColumn('orders', 'delivered_at');
            } catch (\Throwable $e) {
                self::$adminColumns = false;
            }
        }

        return self::$adminColumns;
    }

    private static ?bool $claimColumns = null;

    /** False until the phase 5 migration has been run, so the payment screens keep working before that. */
    public static function hasClaimColumns(): bool
    {
        if (self::$claimColumns === null) {
            try {
                self::$claimColumns = Schema::hasColumn('payments', 'service_request_id') && Schema::hasColumn('orders', 'service_request_id');
            } catch (\Throwable $e) {
                self::$claimColumns = false;
            }
        }

        return self::$claimColumns;
    }

    // ----- Order type (phase 5) -----

    public static function orderTypes(): array
    {
        return ['normal' => 'Normal order', 'replacement' => 'Replacement', 'exchange' => 'Exchange'];
    }

    /** Replacement and exchange orders are made from a claim; they are not new sales demand */
    public function isClaimOrder(): bool
    {
        return in_array($this->order_type, ['replacement', 'exchange'], true);
    }

    public function typeLabel(): string
    {
        return self::orderTypes()[$this->order_type ?: 'normal'] ?? ucfirst((string) $this->order_type);
    }

    /**
     * Only real customer orders (not replacement / exchange orders). Use this where "number of orders" or
     * "sales" is counted, e.g. the dashboard and the future sales reports.
     */
    public function scopeSalesOrders($query)
    {
        if (!self::hasAdminColumns()) {
            return $query;
        }

        return $query->where(function ($w) {
            $w->whereNull('orders.order_type')->orWhere('orders.order_type', 'normal');
        });
    }

    /** Created by staff / owner in the admin panel (not placed by the customer at checkout) */
    public function isAdminCreated(): bool
    {
        return !empty($this->attributes['created_by_name']);
    }

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

    /**
     * Same as NET_PAID_SQL, but money refunded for a CLAIM (phase 5) is left out: such a refund is compensation
     * after delivery, it does not make the customer owe the order total again. Falls back to NET_PAID_SQL
     * until the phase 5 migration has been run.
     */
    public static function netPaidSql(): string
    {
        if (!self::hasClaimColumns()) {
            return self::NET_PAID_SQL;
        }

        return "COALESCE((SELECT SUM(CASE WHEN p.type = 'payment' THEN p.amount WHEN p.service_request_id IS NOT NULL THEN 0 ELSE -p.amount END) FROM payments p WHERE p.order_id = orders.id), 0)";
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function claims()
    {
        return $this->hasMany(ServiceRequest::class, 'order_id');
    }

    /** The claim this replacement / exchange order was made from */
    public function claim()
    {
        return $this->belongsTo(ServiceRequest::class, 'service_request_id');
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    /** Adds paid_in / paid_out columns so lists can show payment status without extra queries */
    public function scopeWithPaid($query)
    {
        $claims = self::hasClaimColumns();

        return $query
            ->withSum(['payments as paid_in'  => fn ($q) => $q->where('type', 'payment')], 'amount')
            ->withSum(['payments as paid_out' => function ($q) use ($claims) {
                $q->where('type', 'refund');
                if ($claims) {
                    $q->whereNull('service_request_id'); // claim refunds are shown separately (see claimRefunded())
                }
            }], 'amount');
    }

    public function scopeOwing($query)       { return $query->whereRaw('orders.total > ' . self::netPaidSql()); }
    public function scopeSettled($query)     { return $query->whereRaw('orders.total <= ' . self::netPaidSql()); }
    public function scopeNothingPaid($query) { return $query->whereRaw(self::netPaidSql() . ' <= 0'); }
    public function scopePartlyPaid($query)  { return $query->whereRaw(self::netPaidSql() . ' > 0 AND orders.total > ' . self::netPaidSql()); }

    /** Money received minus money refunded (refunds given for a claim are not counted here, see claimRefunded()) */
    public function netPaid(): int
    {
        if (array_key_exists('paid_in', $this->attributes)) {
            return (int) $this->attributes['paid_in'] - (int) ($this->attributes['paid_out'] ?? 0);
        }
        if ($this->relationLoaded('payments')) {
            return (int) $this->payments->where('type', 'payment')->sum('amount')
                - (int) $this->payments->where('type', 'refund')->whereNull('service_request_id')->sum('amount');
        }

        $refunds = $this->payments()->where('type', 'refund');
        if (self::hasClaimColumns()) {
            $refunds->whereNull('service_request_id');
        }

        return (int) $this->payments()->where('type', 'payment')->sum('amount') - (int) $refunds->sum('amount');
    }

    /** Money given back to the customer because of a claim (the order total and invoice stay as they were) */
    public function claimRefunded(): int
    {
        if (!self::hasClaimColumns()) {
            return 0;
        }
        if ($this->relationLoaded('payments')) {
            return (int) $this->payments->where('type', 'refund')->whereNotNull('service_request_id')->sum('amount');
        }

        return (int) $this->payments()->where('type', 'refund')->whereNotNull('service_request_id')->sum('amount');
    }

    /** What we really hold for this order right now: everything received minus EVERY refund (the most we can still refund) */
    public function moneyHeld(): int
    {
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
            $changes = ['status' => $new];
            // First time the order becomes "delivered": remember when (warranty starts from this date)
            if ($new === 'delivered' && self::hasAdminColumns() && empty($this->attributes['delivered_at'])) {
                $changes['delivered_at'] = now();
            }
            $this->update($changes);

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
