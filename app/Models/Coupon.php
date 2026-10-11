<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = [
        'code', 'type', 'value', 'min_order', 'max_discount', 'usage_limit', 'used_count',
        'starts_at', 'expires_at', 'is_active', 'note',
    ];

    protected $casts = [
        'starts_at'  => 'datetime',
        'expires_at' => 'datetime',
        'is_active'  => 'boolean',
    ];

    public static function types(): array
    {
        return ['percent' => 'Percentage (%)', 'fixed' => 'Fixed amount (৳)'];
    }

    /** SAVE 10 / save10 -> SAVE10 */
    public static function normalize(?string $code): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim((string) $code)));
    }

    public static function findByCode(?string $code): ?self
    {
        $code = self::normalize($code);

        return $code === '' ? null : static::where('code', $code)->first();
    }

    /** "10% off" or "৳ 50 off" */
    public function label(): string
    {
        return $this->type === 'percent'
            ? (int) $this->value . '% off'
            : '৳ ' . number_format($this->value) . ' off';
    }

    /** The money taken off a given subtotal (never more than the subtotal itself) */
    public function discountFor(int $subtotal): int
    {
        if ($subtotal <= 0) {
            return 0;
        }

        if ($this->type === 'percent') {
            $off = (int) floor($subtotal * ((int) $this->value) / 100);
            if ($this->max_discount !== null && (int) $this->max_discount > 0) {
                $off = min($off, (int) $this->max_discount);
            }
        } else {
            $off = (int) $this->value;
        }

        return max(0, min($off, $subtotal));
    }

    /** null = the coupon can be used now, otherwise the reason (shown to the customer) */
    public function problem(int $subtotal): ?string
    {
        if (!$this->is_active) {
            return 'This coupon is not active.';
        }
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return 'This coupon is not valid yet.';
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return 'This coupon has expired.';
        }
        if ($this->usage_limit !== null && (int) $this->used_count >= (int) $this->usage_limit) {
            return 'This coupon has reached its usage limit.';
        }
        if ((int) $this->min_order > 0 && $subtotal < (int) $this->min_order) {
            return 'This coupon needs a minimum order of ৳ ' . number_format($this->min_order) . '.';
        }

        return null;
    }

    /** active / inactive / scheduled / expired / used_up */
    public function statusKey(): string
    {
        if (!$this->is_active) {
            return 'inactive';
        }
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return 'scheduled';
        }
        if ($this->expires_at && $this->expires_at->isPast()) {
            return 'expired';
        }
        if ($this->usage_limit !== null && (int) $this->used_count >= (int) $this->usage_limit) {
            return 'used_up';
        }

        return 'active';
    }

    public function statusLabel(): string
    {
        return ['active' => 'Active', 'inactive' => 'Inactive', 'scheduled' => 'Scheduled', 'expired' => 'Expired', 'used_up' => 'Used up'][$this->statusKey()];
    }

    public function statusBadge(): string
    {
        return ['active' => 'st-delivered', 'inactive' => 'st-cancelled', 'scheduled' => 'st-confirmed', 'expired' => 'st-returned', 'used_up' => 'st-pending'][$this->statusKey()];
    }
}
