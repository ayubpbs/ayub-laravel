<?php

namespace App\Support;

use App\Models\Product;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Which warranty applies to a product, and whether a claim is inside it.
 *
 *  - A product can carry its own policy (type + days + terms). Nothing is hard-coded.
 *  - A product WITHOUT its own policy uses the shop default from Settings (warranty_enabled / warranty_days /
 *    warranty_terms); that default allows repair or replacement.
 *  - The policy is copied onto every order line when an admin creates the order (a snapshot), so changing a
 *    product later never changes the warranty of goods that were already sold.
 *  - The warranty runs N days from the delivery date; the last valid day is delivery date + N days.
 *
 * The calculation methods have no database access, so they can be tested on their own.
 */
class WarrantyPolicy
{
    /** The warranty types an admin can pick for a product. "none" and "default" are handled separately. */
    public static function types(): array
    {
        return [
            'replacement' => 'Replacement warranty',
            'repair'      => 'Repair / service warranty',
            'any'         => 'Repair or replacement',
        ];
    }

    public static function typeLabel(?string $type): string
    {
        if ($type === null || $type === '' || $type === 'none') {
            return 'No warranty';
        }

        return self::types()[$type] ?? ucfirst($type);
    }

    /** What may be promised to the customer under this policy type (rejection is always possible) */
    public static function allowedResolutions(?string $type): array
    {
        return [
            'replacement' => ['replacement'],
            'repair'      => ['repair'],
            'any'         => ['repair', 'replacement'],
        ][$type] ?? [];
    }

    private static ?bool $ready = null;

    /** False until "php artisan migrate" has been run for the warranty columns */
    public static function columnsReady(): bool
    {
        if (self::$ready === null) {
            try {
                self::$ready = Schema::hasColumn('products', 'warranty_type')
                    && Schema::hasColumn('order_items', 'warranty_source')
                    && Schema::hasColumn('service_requests', 'warranty_type');
            } catch (\Throwable $e) {
                self::$ready = false;
            }
        }

        return self::$ready;
    }

    /** @return array ['type' => none|replacement|repair|any, 'days' => int, 'terms' => ?string, 'source' => product|shop] */
    public static function forProduct(?Product $product): array
    {
        $own = $product ? (string) $product->getAttribute('warranty_type') : '';

        if ($own === 'none') {
            return ['type' => 'none', 'days' => 0, 'terms' => null, 'source' => 'product'];
        }

        $shopDays  = (int) Setting::get('warranty_days', 0);
        $shopTerms = trim((string) Setting::get('warranty_terms', ''));

        if (array_key_exists($own, self::types())) {
            $days  = (int) $product->getAttribute('warranty_days') ?: $shopDays;
            $terms = trim((string) $product->getAttribute('warranty_terms')) ?: $shopTerms;

            return $days > 0
                ? ['type' => $own, 'days' => $days, 'terms' => $terms !== '' ? $terms : null, 'source' => 'product']
                : ['type' => 'none', 'days' => 0, 'terms' => null, 'source' => 'product'];
        }

        if (Setting::bool('warranty_enabled', false) && $shopDays > 0) {
            return ['type' => 'any', 'days' => $shopDays, 'terms' => $shopTerms !== '' ? $shopTerms : null, 'source' => 'shop'];
        }

        return ['type' => 'none', 'days' => 0, 'terms' => null, 'source' => 'shop'];
    }

    /** The columns copied onto an order line when the order is created */
    public static function snapshotFor(?Product $product): array
    {
        $p = self::forProduct($product);

        return [
            'warranty_type'   => $p['type'],
            'warranty_days'   => $p['days'] > 0 ? $p['days'] : null,
            'warranty_terms'  => $p['terms'],
            'warranty_source' => $p['source'],
        ];
    }

    /**
     * Is a claim made at $at inside the warranty?
     *
     * @param array       $policy ['type' => ..., 'days' => ...]
     * @param Carbon|null $start  delivery date (null = not known)
     * @return array state (in_warranty | expired | not_covered | unknown), expires (Carbon|null), days_left (int|null), message
     */
    public static function evaluate(array $policy, ?Carbon $start, Carbon $at): array
    {
        $type = (string) ($policy['type'] ?? 'none');
        $days = (int) ($policy['days'] ?? 0);

        if ($type === 'none' || $days <= 0) {
            return ['state' => 'not_covered', 'expires' => null, 'days_left' => null,
                'message' => 'This product has no warranty, so a warranty claim cannot be accepted.'];
        }
        if ($start === null) {
            return ['state' => 'unknown', 'expires' => null, 'days_left' => null,
                'message' => 'The delivery date of this order is not recorded. Enter the date the customer received the product.'];
        }

        $expires  = $start->copy()->startOfDay()->addDays($days)->endOfDay();
        $daysLeft = (int) $at->copy()->startOfDay()->diffInDays($expires->copy()->startOfDay(), false);

        if ($at->lte($expires)) {
            return ['state' => 'in_warranty', 'expires' => $expires, 'days_left' => $daysLeft,
                'message' => 'Inside the ' . $days . '-day warranty until ' . $expires->format('d M Y') . ' (' . $daysLeft . ' day(s) left).'];
        }

        return ['state' => 'expired', 'expires' => $expires, 'days_left' => $daysLeft,
            'message' => 'The ' . $days . '-day warranty ended on ' . $expires->format('d M Y') . ' (' . abs($daysLeft) . ' day(s) ago).'];
    }
}
