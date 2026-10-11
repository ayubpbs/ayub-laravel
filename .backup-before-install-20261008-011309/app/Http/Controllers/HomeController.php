<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;

class HomeController extends Controller
{
    public function index()
    {
        $categories = Category::with(['products' => function ($q) {
            $q->published()->latestFirst();
        }])->orderBy('sort_order')->orderBy('id')->get();

        $topProducts      = Product::published()->where('section', 'top')->latestFirst()->get();
        $newProducts      = Product::published()->where('section', 'new')->latestFirst()->get();
        $upcomingProducts = Product::published()->where('is_upcoming', 1)->latestFirst()->get();

        return view('shop.home', compact('categories', 'topProducts', 'newProducts', 'upcomingProducts'));
    }
}
