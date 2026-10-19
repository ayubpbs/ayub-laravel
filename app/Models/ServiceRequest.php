<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * A claim on a delivered order: wrong product, defective / damaged goods, or an exchange request.
 * The original order and its invoice are never edited; the claim only points at them.
 */
class ServiceRequest extends Model
{
    protected $fillable = [
        'order_id', 'customer_name', 'customer_phone', 'type', 'status', 'reason', 'return_required', 'resolution',
        'decision_note', 'decided_by_id', 'decided_by_name', 'decided_at', 'replacement_order_id', 'exchange_credit',
        'opened_by_id', 'opened_by_name', 'closed_at', 'closed_by_name',
        // Phase 6: warranty claims
        'warranty_type', 'warranty_days', 'warranty_terms', 'warranty_source', 'warranty_start_at', 'warranty_start_source',
        'warranty_expires_at', 'warranty_state', 'serial_number', 'replacement_serial', 'symptoms', 'received_condition',
        'inspection_result', 'inspection_note', 'inspected_at', 'inspected_by_name',
    ];

    protected $casts = [
        'return_required' => 'boolean',
        'decided_at'      => 'datetime',
        'closed_at'       => 'datetime',
        'warranty_start_at'   => 'datetime',
        'warranty_expires_at' => 'datetime',
        'inspected_at'        => 'datetime',
    ];

    private static ?bool $ready = null;

    /** False until "php artisan migrate" has been run for phase 5 */
    public static function tableReady(): bool
    {
        if (self::$ready === null) {
            try {
                self::$ready = Schema::hasTable('service_requests') && Schema::hasColumn('orders', 'service_request_id')
                    && Schema::hasColumn('payments', 'service_request_id');
            } catch (\Throwable $e) {
                self::$ready = false;
            }
        }

        return self::$ready;
    }

    public static function types(): array
    {
        return [
            'wrong_product' => 'Wrong product received',
            'defective'     => 'Defective / not working',
            'damaged'       => 'Damaged in delivery',
            'exchange'      => 'Exchange request',
            'other'         => 'Other',
        ];
    }

    /** Every type including warranty (types() is what the exchange form offers: warranty has its own form) */
    public static function allTypes(): array
    {
        return self::types() + ['warranty' => 'Warranty claim'];
    }

    /** What can be promised for a warranty claim (which of these is allowed also depends on the product's policy) */
    public static function warrantyResolutions(): array
    {
        return [
            'repair'      => 'Repair / service',
            'replacement' => 'Replacement (same product, free of charge)',
        ];
    }

    /** Result of looking at the returned unit */
    public static function inspectionResults(): array
    {
        return [
            'defect_confirmed' => 'Defect confirmed (covered by the warranty)',
            'physical_damage'  => 'Physical, burn or water damage (not covered)',
            'no_fault'         => 'No fault found',
            'other'            => 'Other (not covered)',
        ];
    }

    public function isWarranty(): bool
    {
        return $this->type === 'warranty';
    }

    public static function statuses(): array
    {
        return ['open' => 'Open', 'approved' => 'Approved', 'rejected' => 'Rejected', 'resolved' => 'Resolved'];
    }

    public static function resolutions(): array
    {
        return [
            'replacement' => 'Replacement (same goods, free of charge)',
            'exchange'    => 'Exchange (other goods, price difference)',
            'refund'      => 'Refund (money back)',
        ];
    }

    /** Claim statuses that still "use up" the claimed quantity of an order line */
    public static function activeStatuses(): array
    {
        return ['open', 'approved', 'resolved'];
    }

    /** Same colours as the order status chips */
    public static function badge(string $status): string
    {
        return ['open' => 'st-pending', 'approved' => 'st-confirmed', 'rejected' => 'st-cancelled', 'resolved' => 'st-delivered'][$status] ?? 'st-pending';
    }

    public function typeLabel(): string
    {
        return self::allTypes()[$this->type] ?? ucfirst((string) $this->type);
    }

    public function statusLabel(): string
    {
        return self::statuses()[$this->status] ?? ucfirst((string) $this->status);
    }

    public function resolutionLabel(): string
    {
        return $this->resolution ? (self::resolutions()[$this->resolution] ?? self::warrantyResolutions()[$this->resolution] ?? $this->resolution) : '—';
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function replacementOrder()
    {
        return $this->belongsTo(Order::class, 'replacement_order_id');
    }

    public function items()
    {
        return $this->hasMany(ServiceRequestItem::class);
    }

    public function files()
    {
        return $this->hasMany(ServiceRequestFile::class)->orderBy('id');
    }

    public function shipments()
    {
        return $this->hasMany(ServiceRequestShipment::class)->orderBy('id');
    }

    public function refunds()
    {
        return $this->hasMany(Payment::class, 'service_request_id')->where('type', 'refund');
    }

    /** Money given back for this claim so far */
    public function refundedAmount(): int
    {
        return (int) $this->refunds()->sum('amount');
    }

    /** Value of the claimed goods at the price the customer paid */
    public function itemsValue(): int
    {
        return (int) $this->items->sum(fn ($i) => (int) $i->price * (int) $i->quantity);
    }

    /** True when every claimed item has been inspected */
    public function fullyInspected(): bool
    {
        return $this->items->isNotEmpty() && $this->items->every(fn ($i) => $i->isInspected());
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['open', 'approved'], true);
    }
}
