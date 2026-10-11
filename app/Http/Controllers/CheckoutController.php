<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Support\BdLocations;
use App\Support\Cart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    /** Session key holding the one coupon code applied to the current order */
    private const COUPON_KEY = 'checkout_coupon';

    public function show()
    {
        $lines = Cart::lines();
        if (!$lines) {
            return redirect()->route('cart.index');
        }

        $s = $this->summary($lines);

        return view('checkout.show', [
            'lines'     => $lines,
            'subtotal'  => $s['subtotal'],
            'discount'  => $s['discount'],
            'coupon'    => $s['coupon'],
            'notice'    => $s['notice'],
            'rates'     => Cart::rates(),
            'locations' => BdLocations::all(),
        ]);
    }

    // ----- Live updates from the checkout page (JSON) -----

    public function qty(Request $request, $id)
    {
        $id = (int) $id;

        if (isset(Cart::raw()[$id])) {
            $product = Product::published()->find($id);
            $qty     = (int) $request->input('quantity', 1);

            if (!$product || $qty <= 0) {
                Cart::remove($id);
            } else {
                Cart::set($id, min($qty, (int) $product->stock_quantity));
            }
        }

        return $this->json();
    }

    public function remove($id)
    {
        Cart::remove((int) $id);

        return $this->json();
    }

    public function applyCoupon(Request $request)
    {
        $lines    = Cart::lines();
        $subtotal = Cart::subtotal($lines);

        if (!$lines) {
            return $this->json(['ok' => false, 'message' => 'Your cart is empty.'], 422);
        }

        // One coupon per order: a second one cannot be added until the first is removed
        if (session(self::COUPON_KEY)) {
            return $this->json(['ok' => false, 'message' => 'A coupon is already applied to this order. Remove it first to use a different one.'], 422);
        }

        $code = Coupon::normalize($request->input('code'));
        if ($code === '') {
            return $this->json(['ok' => false, 'message' => 'Please enter a coupon code.'], 422);
        }

        $coupon = Coupon::findByCode($code);
        $error  = $coupon ? $coupon->problem($subtotal) : 'This coupon code is not valid.';
        if ($error) {
            return $this->json(['ok' => false, 'message' => $error], 422);
        }

        session([self::COUPON_KEY => $coupon->code]);

        return $this->json([
            'ok'      => true,
            'message' => 'Coupon ' . $coupon->code . ' applied — you saved ৳ ' . number_format($coupon->discountFor($subtotal)) . '.',
        ]);
    }

    public function removeCoupon()
    {
        session()->forget(self::COUPON_KEY);

        return $this->json(['ok' => true, 'message' => 'Coupon removed.']);
    }

    // ----- Placing the order -----

    public function store(Request $request)
    {
        $request->merge(['phone' => $this->normalizePhone((string) $request->input('phone'))]);

        $data = $request->validate([
            'name'     => 'required|string|max:100',
            'phone'    => ['required', 'regex:/^01[3-9]\d{8}$/'],
            'district' => ['required', Rule::in(BdLocations::districtNames())],
            'upazila'  => 'required|string|max:100',
            'city'     => 'required|string|max:100',
            'address'  => 'required|string|max:500',
            'note'     => 'nullable|string|max:500',
            'terms'    => 'accepted',
        ], [
            'name.required'     => 'Please enter your name.',
            'phone.required'    => 'Please enter your mobile number.',
            'phone.regex'       => 'Please enter a valid mobile number (e.g. 01712345678).',
            'district.required' => 'Please select your district.',
            'district.in'       => 'Please select a valid district.',
            'upazila.required'  => 'Please select your upazila.',
            'city.required'     => 'Please enter your city or town.',
            'address.required'  => 'Please enter your delivery address.',
            'terms.accepted'    => 'Please accept the Terms & Conditions, Privacy Policy, Return Policy and Delivery Policy to place your order.',
        ]);

        if (!BdLocations::hasUpazila($data['district'], $data['upazila'])) {
            throw ValidationException::withMessages(['upazila' => 'Please select a valid upazila for the chosen district.']);
        }

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
                        'cost_price' => $p->cost_price,
                        'quantity'   => $qty,
                        'line_total' => $lineTotal,
                    ];

                    $p->decrement('stock_quantity', $qty);
                }

                // Coupon: checked again here, with the row locked, so the usage limit can never be exceeded
                $discount   = 0;
                $couponCode = null;
                $code       = session(self::COUPON_KEY);
                if ($code) {
                    $coupon = Coupon::where('code', $code)->lockForUpdate()->first();
                    $error  = $coupon ? $coupon->problem($subtotal) : 'This coupon code is not valid.';
                    if ($error) {
                        throw new \DomainException($error);
                    }
                    $discount   = $coupon->discountFor($subtotal);
                    $couponCode = $coupon->code;
                    $coupon->increment('used_count');
                }

                $delivery = Cart::delivery($subtotal, $data['district']);

                $order = Order::create([
                    'name'            => $data['name'],
                    'phone'           => $data['phone'],
                    'district'        => $data['district'],
                    'upazila'         => $data['upazila'],
                    'city'            => $data['city'],
                    // Full address in one line, so the invoice and the courier get everything
                    'address'         => implode(', ', array_filter([$data['address'], $data['city'], $data['upazila'], $data['district']])),
                    'note'            => $data['note'] ?? null,
                    'subtotal'        => $subtotal,
                    'coupon_code'     => $couponCode,
                    'discount'        => $discount,
                    'delivery_charge' => $delivery,
                    'total'           => $subtotal - $discount + $delivery,
                    'status'          => 'pending',
                ]);

                $order->items()->createMany($rows);

                return $order;
            });
        } catch (\DomainException $e) {
            // The coupon stopped being valid while the customer was checking out
            return redirect()->route('checkout.show')->withInput()->with('error', 'Your coupon could not be used: ' . $e->getMessage());
        } catch (\RuntimeException $e) {
            return redirect()->route('cart.index')->with('error', $e->getMessage());
        }

        // Admin notifications: the new order, and any product that ran low because of it
        \App\Support\Notify::newOrder($order);
        foreach (Product::whereIn('id', array_keys($cart))->get() as $p) {
            \App\Support\Notify::lowStock($p);
        }

        Cart::clear();
        session()->forget(self::COUPON_KEY);
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

    // ----- Helpers -----

    /**
     * Subtotal and discount of the current cart. A coupon that is no longer valid
     * (for example the subtotal dropped below its minimum) is dropped with a notice.
     */
    private function summary(array $lines): array
    {
        $subtotal = Cart::subtotal($lines);
        $discount = 0;
        $coupon   = null;
        $notice   = null;

        $code = session(self::COUPON_KEY);
        if ($code && $subtotal > 0) {
            $found = Coupon::findByCode($code);
            $error = $found ? $found->problem($subtotal) : 'This coupon code is not valid.';

            if ($error) {
                session()->forget(self::COUPON_KEY);
                $notice = 'Coupon ' . $code . ' was removed. ' . $error;
            } else {
                $coupon   = $found;
                $discount = $found->discountFor($subtotal);
            }
        } elseif ($code) {
            session()->forget(self::COUPON_KEY);
        }

        return compact('subtotal', 'discount', 'coupon', 'notice');
    }

    /** Current cart state for the checkout page JavaScript */
    private function json(array $extra = [], int $status = 200)
    {
        $lines = Cart::lines();

        if (!$lines) {
            return response()->json(['empty' => true, 'redirect' => route('cart.index')] + $extra, $status);
        }

        $s = $this->summary($lines);

        return response()->json($extra + [
            'empty'    => false,
            'count'    => Cart::count(),
            'subtotal' => $s['subtotal'],
            'discount' => $s['discount'],
            'coupon'   => $s['coupon'] ? ['code' => $s['coupon']->code, 'label' => $s['coupon']->label()] : null,
            'notice'   => $s['notice'],
            'items'    => array_map(fn ($l) => [
                'id'    => $l['product']->id,
                'qty'   => $l['qty'],
                'total' => $l['total'],
            ], $lines),
        ], $status);
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
