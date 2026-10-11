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

        // The main category (the category itself, or its parent when this is a subcategory)
        $root          = $category->parent_id ? (Category::find($category->parent_id) ?: $category) : $category;
        $subcategories = $root->children()->get();

        // A main category shows its own products plus those of all its subcategories
        $ids = $category->parent_id
            ? [$category->id]
            : $subcategories->pluck('id')->push($category->id)->all();

        $products = Product::published()->whereIn('category_id', $ids)
            ->latestFirst()->paginate(24);
        $title = $category->label;

        return view('shop.category', compact('products', 'title', 'category', 'root', 'subcategories'));
    }
}
