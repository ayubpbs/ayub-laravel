<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class ProductReview extends Model
{
    const PENDING  = 'pending';
    const APPROVED = 'approved';
    const REJECTED = 'rejected';

    protected $fillable = ['product_id', 'name', 'rating', 'comment', 'status'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::APPROVED);
    }

    /** True when the product_reviews table exists (so the site keeps working before the migration is run). */
    public static function available(): bool
    {
        static $ok = null;

        if ($ok === null) {
            try {
                $ok = Schema::hasTable('product_reviews');
            } catch (\Throwable $e) {
                $ok = false;
            }
        }

        return $ok;
    }

    public static function pendingCount(): int
    {
        if (!self::available()) {
            return 0;
        }

        try {
            return (int) self::where('status', self::PENDING)->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    public function stars(): string
    {
        $n = max(0, min(5, (int) $this->rating));

        return str_repeat('★', $n) . str_repeat('☆', 5 - $n);
    }

    /**
     * Writes the average rating and the number of approved reviews onto the product,
     * so product cards and the product page header always match the real reviews.
     */
    public static function refreshProduct(int $productId): void
    {
        $row   = self::approved()->where('product_id', $productId)
            ->selectRaw('COUNT(*) as c, AVG(rating) as a')->first();
        $count = (int) ($row->c ?? 0);
        $avg   = $count > 0 ? round((float) $row->a, 1) : 0;

        Product::whereKey($productId)->update(['rating' => $avg, 'reviews' => $count]);
    }
}
