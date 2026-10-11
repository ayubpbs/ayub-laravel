<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductImage extends Model
{
    const DIR = 'uploads/products/gallery';

    protected $fillable = ['product_id', 'image', 'sort_order'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function url(): string
    {
        return asset(self::DIR . '/' . $this->image);
    }
}
