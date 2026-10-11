<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id', 'brand_id', 'title', 'brand', 'description', 'icon', 'image',
        'price', 'old_price', 'badge', 'rating', 'reviews', 'section',
        'stock_quantity', 'is_upcoming', 'is_published', 'published_at',
    ];

    protected $casts = [
        'is_upcoming'  => 'boolean',
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function brandItem()
    {
        return $this->belongsTo(Brand::class, 'brand_id');
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', 1);
    }

    public function scopeLatestFirst($query)
    {
        return $query->orderByDesc('published_at')->orderByDesc('id');
    }

    public function inStock(): bool
    {
        return (int) $this->stock_quantity > 0;
    }

    public function stars(): string
    {
        $r    = (float) $this->rating;
        $full = (int) floor($r);
        $half = ($r - $full) >= 0.5;
        return str_repeat('★', $full) . ($half ? '⯨' : '') . str_repeat('☆', 5 - $full - ($half ? 1 : 0));
    }
}
