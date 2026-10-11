<?php

namespace App\Support;

use App\Models\Product;

/**
 * Session-based cart. The session only stores [product id => quantity].
 * Prices are always read from the database, so customers cannot change them.
 */
class Cart
{
    const KEY = 'cart';

    public static function raw(): array
    {
        return array_filter(session(self::KEY, []), fn ($q) => (int) $q > 0);
    }

    public static function count(): int
    {
        return (int) array_sum(self::raw());
    }

    public static function set(int $id, int $qty): void
    {
        $cart = self::raw();
        if ($qty <= 0) {
            unset($cart[$id]);
        } else {
            $cart[$id] = $qty;
        }
        session([self::KEY => $cart]);
    }

    public static function remove(int $id): void
    {
        self::set($id, 0);
    }

    public static function clear(): void
    {
        session()->forget(self::KEY);
    }

    /**
     * The cart lines. Products that can no longer be bought (deleted, out of stock, upcoming)
     * are dropped from the cart, and quantities above the available stock are lowered to the stock level.
     */
    public static function lines(): array
    {
        $cart = self::raw();
        if (!$cart) {
            return [];
        }

        $products = Product::published()->whereIn('id', array_keys($cart))->get()->keyBy('id');

        $lines = [];
        $fixed = $cart;
        foreach ($cart as $id => $qty) {
            $p = $products->get($id);
            if (!$p || $p->is_upcoming || !$p->inStock()) {
                unset($fixed[$id]);
                continue;
            }
            $qty = min((int) $qty, (int) $p->stock_quantity);
            $fixed[$id] = $qty;
            $lines[] = ['product' => $p, 'qty' => $qty, 'total' => $p->price * $qty];
        }

        if ($fixed !== $cart) {
            session([self::KEY => $fixed]);
        }

        return $lines;
    }

    public static function subtotal(array $lines): int
    {
        return (int) array_sum(array_column($lines, 'total'));
    }

    /** Delivery charges set in Admin > Settings (defaults: ৳ 80 inside Dhaka, ৳ 100 outside Dhaka) */
    public static function rates(): array
    {
        return [
            'inside'  => (int) \App\Models\Setting::get('delivery_inside_dhaka', 80),
            'outside' => (int) \App\Models\Setting::get('delivery_outside_dhaka', 100),
        ];
    }

    /** Inside Dhaka = the district "Dhaka"; every other district is outside Dhaka */
    public static function isInsideDhaka(?string $district): bool
    {
        return $district === 'Dhaka';
    }

    /** The delivery charge depends on the district, so it is 0 until a district is known */
    public static function delivery(int $subtotal, ?string $district = null): int
    {
        if ($subtotal <= 0 || $district === null || $district === '') {
            return 0;
        }

        $rates = self::rates();

        return self::isInsideDhaka($district) ? $rates['inside'] : $rates['outside'];
    }
}
