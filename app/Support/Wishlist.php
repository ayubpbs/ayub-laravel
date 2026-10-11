<?php

namespace App\Support;

use App\Models\Product;

/**
 * Session-based wishlist (the shop has no customer accounts, same as the cart).
 * The session only stores a list of product ids, newest first.
 */
class Wishlist
{
    const KEY = 'wishlist';
    const MAX = 100;

    public static function ids(): array
    {
        return array_values(array_unique(array_map('intval', (array) session(self::KEY, []))));
    }

    public static function count(): int
    {
        return count(self::ids());
    }

    public static function has(int $id): bool
    {
        return in_array($id, self::ids(), true);
    }

    /** Adds the product when it is not in the wishlist, removes it when it is. Returns true when it is now wished. */
    public static function toggle(int $id): bool
    {
        $ids = self::ids();

        if (in_array($id, $ids, true)) {
            $ids = array_values(array_diff($ids, [$id]));
            $wished = false;
        } else {
            array_unshift($ids, $id);
            $ids = array_slice($ids, 0, self::MAX);
            $wished = true;
        }

        session([self::KEY => $ids]);

        return $wished;
    }

    /** The published products of the wishlist, newest added first (deleted or hidden products are dropped). */
    public static function products()
    {
        $ids = self::ids();
        if (!$ids) {
            return collect();
        }

        $found = Product::published()->whereIn('id', $ids)->get()->keyBy('id');

        $products = collect();
        foreach ($ids as $id) {
            if ($found->has($id)) {
                $products->push($found->get($id));
            }
        }

        // Forget products that no longer exist
        if ($products->count() !== count($ids)) {
            session([self::KEY => $products->pluck('id')->all()]);
        }

        return $products;
    }
}
