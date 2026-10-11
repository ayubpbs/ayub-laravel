<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    // All products (and search: /category?q=fan)
    public function index(Request $request)
    {
        $q     = trim((string) $request->query('q', ''));
        $query = Product::published()->latestFirst();

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('title', 'like', "%{$q}%")->orWhere('brand', 'like', "%{$q}%");
            });
        }

        $products = $query->paginate(24)->withQueryString();
        $title    = $q !== '' ? "Search: {$q}" : 'All Products';

        return view('shop.category', compact('products', 'title'));
    }

    public function show($id)
    {
        $category = Category::findOrFail($id);
        $products = Product::published()->where('category_id', $category->id)
            ->latestFirst()->paginate(24);
        $title = $category->label;

        return view('shop.category', compact('products', 'title'));
    }
}
