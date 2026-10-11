<?php

namespace App\Http\Controllers;

use App\Models\Product;

class ProductController extends Controller
{
    public function show($id)
    {
        $product = Product::published()->with('category')->findOrFail($id);

        $related = Product::published()
            ->where('category_id', $product->category_id)
            ->where('id', '<>', $product->id)
            ->latestFirst()->limit(4)->get();

        return view('shop.product', compact('product', 'related'));
    }
}
