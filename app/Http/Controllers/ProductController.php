<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;

class ProductController extends Controller
{
    public function show($key)
    {
        $query = Product::published()->with(['category.parent', 'brandItem']);

        if (ctype_digit((string) $key)) {
            // Old address with the product number: open it, or move to the slug address when the product has one
            $product = $query->findOrFail($key);
            if (($product->slug ?? '') !== '') {
                return redirect()->to($product->url(), 301);
            }
        } else {
            $product = $query->where('slug', mb_strtolower((string) $key))->firstOrFail();
        }

        // Breadcrumb trail: main category -> subcategory (a main category is just one step)
        $crumbs = [];
        if ($product->category) {
            if ($product->category->parent) {
                $crumbs[] = $product->category->parent;
            }
            $crumbs[] = $product->category;
        }

        $related = $this->related($product);

        return view('shop.product', compact('product', 'related', 'crumbs'));
    }

    /**
     * Same category first; when that gives fewer than 4, top up with products
     * from the rest of the same main category (its subcategories).
     */
    private function related(Product $product)
    {
        if (!$product->category_id) {
            return collect();
        }

        $related = Product::published()
            ->where('category_id', $product->category_id)
            ->where('id', '<>', $product->id)
            ->latestFirst()->limit(8)->get();

        if ($related->count() < 4) {
            $rootId = optional($product->category)->parent_id ?: $product->category_id;
            $family = Category::where('parent_id', $rootId)->pluck('id')->push($rootId)->all();

            $more = Product::published()
                ->whereIn('category_id', $family)
                ->where('id', '<>', $product->id)
                ->whereNotIn('id', $related->pluck('id'))
                ->latestFirst()->limit(8 - $related->count())->get();

            $related = $related->concat($more);
        }

        return $related;
    }
}
