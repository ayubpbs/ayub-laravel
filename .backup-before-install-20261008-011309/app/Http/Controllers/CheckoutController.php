<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Support\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckoutController extends Controller
{
    public function show()
    {
        $lines = Cart::lines();
        if (!$lines) {
            return redirect()->route('cart.index');
        }

        $subtotal = Cart::subtotal($lines);
        $delivery = Cart::delivery($subtotal);

        return view('checkout.show', [
            'lines'    => $lines,
            'subtotal' => $subtotal,
            'delivery' => $delivery,
            'total'    => $subtotal + $delivery,
        ]);
    }

    public function store(Request $request)
    {
        $request->merge(['phone' => $this->normalizePhone((string) $request->input('phone'))]);

        $data = $request->validate([
            'name'    => 'required|string|max:100',
            'phone'   => ['required', 'regex:/^01[3-9]\d{8}$/'],
            'address' => 'required|string|max:500',
            'note'    => 'nullable|string|max:500',
        ], [
            'name.required'    => 'Please enter your name.',
            'phone.required'   => 'Please enter your mobile number.',
            'phone.regex'      => 'Please enter a valid mobile number (e.g. 01712345678).',
            'address.required' => 'Please enter your delivery address.',
        ]);

        $cart = Cart::raw();
        if (!$cart) {
            return redirect()->route('cart.index');
        }

        try {
            $order = DB::transaction(function () use ($data, $cart) {
                // Lock the products so stock stays correct if someone else buys at the same time
                $products = Product::whereIn('id', array_keys($cart))->lockForUpdate()->get()->keyBy('id');

                $rows     = [];
                $subtotal = 0;

                foreach ($cart as $id => $qty) {
                    $p = $products->get($id);

                    if (!$p || !$p->is_published || $p->is_upcoming) {
                        throw new \RuntimeException('An item in your cart is no longer available. Please review your cart and try again.');
                    }
                    if ((int) $p->stock_quantity < $qty) {
                        throw new \RuntimeException('"' . $p->title . '" does not have enough stock (available: ' . $p->stock_quantity . '). Please reduce the quantity and try again.');
                    }

                    $lineTotal = $p->price * $qty;
                    $subtotal += $lineTotal;
                    $rows[]    = [
                        'product_id' => $p->id,
                        'title'      => $p->title,
                        'price'      => $p->price,
                        'quantity'   => $qty,
                        'line_total' => $lineTotal,
                    ];

                    $p->decrement('stock_quantity', $qty);
                }

                $delivery = Cart::delivery($subtotal);

                $order = Order::create([
                    'name'            => $data['name'],
                    'phone'           => $data['phone'],
                    'address'         => $data['address'],
                    'note'            => $data['note'] ?? null,
                    'subtotal'        => $subtotal,
                    'delivery_charge' => $delivery,
                    'total'           => $subtotal + $delivery,
                    'status'          => 'pending',
                ]);

                $order->items()->createMany($rows);

                return $order;
            });
        } catch (\RuntimeException $e) {
            return redirect()->route('cart.index')->with('error', $e->getMessage());
        }

        Cart::clear();
        session(['last_order_id' => $order->id]);

        return redirect()->route('order.success', $order->id);
    }

    public function success($id)
    {
        // Only the person who just placed the order can view this page
        if ((int) session('last_order_id') !== (int) $id) {
            return redirect()->route('home');
        }

        $order = Order::with('items')->findOrFail($id);

        return view('checkout.success', compact('order'));
    }

    /** Converts Bengali digits to English, strips spaces, dashes and +88 to give 01XXXXXXXXX */
    private function normalizePhone(string $phone): string
    {
        $phone = strtr($phone, [
            '০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4',
            '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9',
        ]);
        $phone = preg_replace('/[\s\-]/', '', $phone);

        return preg_replace('/^(\+?88)/', '', $phone);
    }
}
