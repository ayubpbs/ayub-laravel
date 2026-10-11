<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\Wishlist;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index()
    {
        return view('wishlist.index', ['products' => Wishlist::products()]);
    }

    public function toggle(Request $request, $id)
    {
        $product = Product::published()->findOrFail($id);
        $wished  = Wishlist::toggle($product->id);

        $msg = $wished
            ? '♥ "' . $product->title . '" was added to your wishlist.'
            : '"' . $product->title . '" was removed from your wishlist.';

        // For JavaScript (no page reload) requests, return JSON only
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'wished' => $wished, 'message' => $msg, 'count' => Wishlist::count()]);
        }

        return back()->with('success', $msg);
    }
}
