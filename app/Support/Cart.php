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

    public static function delivery(int $subtotal): int
    {
        return $subtotal > 0 ? (int) config('shop.delivery_charge', 0) : 0;
    }
}
