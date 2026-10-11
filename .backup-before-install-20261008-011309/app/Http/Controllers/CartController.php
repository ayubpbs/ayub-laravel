<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\Cart;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function index()
    {
        $lines    = Cart::lines();
        $subtotal = Cart::subtotal($lines);
        $delivery = Cart::delivery($subtotal);

        return view('cart.index', [
            'lines'    => $lines,
            'subtotal' => $subtotal,
            'delivery' => $delivery,
            'total'    => $subtotal + $delivery,
        ]);
    }

    public function add(Request $request, $id)
    {
        $product = Product::published()->findOrFail($id);

        if ($product->is_upcoming || !$product->inStock()) {
            $err = 'Sorry, this product cannot be purchased right now.';
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => $err, 'count' => Cart::count()], 422);
            }
            return back()->with('error', $err);
        }

        $qty     = max(1, (int) $request->input('quantity', 1));
        $current = (int) (Cart::raw()[$product->id] ?? 0);
        $new     = min($current + $qty, (int) $product->stock_quantity);

        Cart::set($product->id, $new);

        if ($request->has('buy_now')) {
            return redirect()->route('checkout.show');
        }

        $msg = '✔ "' . $product->title . '" has been added to your cart.';
        if ($current + $qty > $product->stock_quantity) {
            $msg .= ' (Only ' . $product->stock_quantity . ' left in stock, so the maximum available was added.)';
        }

        // For JavaScript (no page reload) requests, return JSON only
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => $msg, 'count' => Cart::count()]);
        }

        return back()->with('success', $msg);
    }

    public function update(Request $request, $id)
    {
        $product = Product::published()->find($id);
        $qty     = (int) $request->input('quantity', 1);

        if (!$product || $qty <= 0) {
            Cart::remove((int) $id);
        } else {
            Cart::set((int) $id, min($qty, (int) $product->stock_quantity));
        }

        return redirect()->route('cart.index');
    }

    public function remove($id)
    {
        Cart::remove((int) $id);

        return redirect()->route('cart.index')->with('success', 'The product was removed from your cart.');
    }
}
