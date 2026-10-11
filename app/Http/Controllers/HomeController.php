<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;

class HomeController extends Controller
{
    public function index()
    {
        $published = function ($q) {
            $q->published()->latestFirst();
        };

        // Only main categories are listed on the home page.
        // Each one also shows the products of its subcategories.
        $categories = Category::whereNull('parent_id')
            ->with(['products' => $published, 'children.products' => $published])
            ->orderBy('sort_order')->orderBy('id')->get();

        foreach ($categories as $c) {
            $merged = $c->products
                ->concat($c->children->flatMap(function ($child) {
                    return $child->products;
                }))
                ->sort(function ($a, $b) {
                    $ta = optional($a->published_at)->timestamp ?? 0;
                    $tb = optional($b->published_at)->timestamp ?? 0;

                    return [$tb, $b->id] <=> [$ta, $a->id];
                })
                ->values();

            $c->setRelation('products', $merged);
        }

        $topProducts      = Product::published()->where('section', 'top')->latestFirst()->get();
        $newProducts      = Product::published()->where('section', 'new')->latestFirst()->get();
        $upcomingProducts = Product::published()->where('is_upcoming', 1)->latestFirst()->get();

        return view('shop.home', compact('categories', 'topProducts', 'newProducts', 'upcomingProducts'));
    }
}
