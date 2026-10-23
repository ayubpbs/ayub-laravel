<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\CustomerProfile;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Support\AdminAccess;
use App\Support\Audit;
use App\Support\Cart;
use App\Support\Notify;
use App\Support\WarrantyPolicy;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Builds an order for the admin panel (phone / walk-in / reorder).
 *
 * It follows the same rules as the public checkout (CheckoutController@store) - product row locks,
 * stock check and decrement, coupon rules, delivery charge, notifications - but it is a separate class,
 * so the live shop checkout is not touched at all.
 */
class AdminOrderBuilder
{
    /**
     * @param array $data  name, phone (already 01XXXXXXXXX), district, upazila, city, address (street only),
     *                     note, coupon_code, client_token, source_order_id, address_id.
     *                     Phase 5 (replacement / exchange orders made from a claim), all optional:
     *                     order_type (normal|replacement|exchange), service_request_id, address_line (the full address
     *                     as it is, instead of street + city + upazila + district), free_items (every line costs 0),
     *                     credit (taka taken off for returned goods, exchange), delivery_override (taka, no zone price),
     *                     allow_unpublished (the product may be hidden from the shop but must have stock)
     * @param array $items [product_id => quantity]
     * @return array [Order $order, bool $created]  $created is false when the same form was sent twice
     *
     * @throws \RuntimeException  product/stock problem or migration missing (message is shown to the admin)
     * @throws \DomainException   coupon problem (message is shown to the admin)
     */
    public function create(array $data, array $items): array
    {
        if (!Order::hasAdminColumns()) {
            throw new \RuntimeException('The new order columns are missing. Run "php artisan migrate" first.');
        }

        $token = (string) ($data['client_token'] ?? '');

        // Same form sent twice (double click, refresh): return the order that was already made
        if ($token !== '' && ($dup = Order::where('client_token', $token)->first())) {
            return [$dup, false];
        }

        // Merge repeated products into one line
        $cart = [];
        foreach ($items as $id => $qty) {
            $id  = (int) $id;
            $qty = (int) $qty;
            if ($id > 0 && $qty > 0) {
                $cart[$id] = ($cart[$id] ?? 0) + $qty;
            }
        }
        if (!$cart) {
            throw new \RuntimeException('Please add at least one product.');
        }

        $staff  = AdminAccess::staff();
        $byId   = $staff ? $staff->id : null;
        $byName = AdminAccess::name();

        // Phase 5: options used only when an order is made from a claim
        $type = (string) ($data['order_type'] ?? 'normal');
        if (!in_array($type, ['normal', 'replacement', 'exchange'], true)) {
            $type = 'normal';
        }
        $isClaim     = $type !== 'normal';
        $free        = !empty($data['free_items']);
        $credit      = max(0, (int) ($data['credit'] ?? 0));
        $hidden      = !empty($data['allow_unpublished']);
        $claimId     = !empty($data['service_request_id']) ? (int) $data['service_request_id'] : null;
        $deliveryFix = array_key_exists('delivery_override', $data) && $data['delivery_override'] !== null ? max(0, (int) $data['delivery_override']) : null;

        try {
            $order = DB::transaction(function () use ($data, $cart, $token, $byId, $byName, $type, $isClaim, $free, $credit, $hidden, $claimId, $deliveryFix) {
                // Lock the products so stock stays correct if a customer buys at the same time
                $products = Product::whereIn('id', array_keys($cart))->lockForUpdate()->get()->keyBy('id');

                $rows     = [];
                $subtotal = 0;
                $warrantyOn = WarrantyPolicy::columnsReady();   // Phase 6: the warranty of each product is copied onto its order line

                foreach ($cart as $id => $qty) {
                    $p = $products->get($id);

                    if (!$p || (!$p->is_published && !$hidden) || $p->is_upcoming) {
                        throw new \RuntimeException('A product in this order is not available any more. Please remove it and try again.');
                    }
                    if ((int) $p->stock_quantity < $qty) {
                        throw new \RuntimeException('"' . $p->title . '" does not have enough stock (available: ' . (int) $p->stock_quantity . ').');
                    }

                    $unit      = $free ? 0 : (int) $p->price;
                    $lineTotal = $unit * $qty;
                    $subtotal += $lineTotal;
                    $rows[]    = [
                        'product_id' => $p->id,
                        'title'      => $p->title,
                        'price'      => $unit,
                        'quantity'   => $qty,
                        'line_total' => $lineTotal,
                    ] + ($warrantyOn ? WarrantyPolicy::snapshotFor($p) : [])
                      // Buying price snapshot for the Profit / Loss report (free items still cost the shop their real buying price)
                      + (OrderItem::hasCostColumn() ? ['cost_price' => $p->cost_price] : []);

                    $p->decrement('stock_quantity', $qty);
                }

                // Coupon: checked here with the row locked, so the usage limit can never be passed
                $discount   = 0;
                $couponCode = null;
                $code       = $isClaim ? '' : Coupon::normalize($data['coupon_code'] ?? '');
                if ($code !== '') {
                    $coupon = Coupon::where('code', $code)->lockForUpdate()->first();
                    $error  = $coupon ? $coupon->problem($subtotal) : 'This coupon code is not valid.';
                    if ($error) {
                        throw new \DomainException($error);
                    }
                    $discount   = $coupon->discountFor($subtotal);
                    $couponCode = $coupon->code;
                    $coupon->increment('used_count');
                }

                // Exchange: the value of the goods that come back is taken off the new goods
                if ($credit > 0) {
                    $discount = min($credit, $subtotal);
                }

                $delivery = $deliveryFix !== null ? $deliveryFix : Cart::delivery($subtotal, $data['district']);

                $order = Order::create([
                    'name'            => $data['name'],
                    'phone'           => $data['phone'],
                    'district'        => $data['district'],
                    'upazila'         => $data['upazila'],
                    'city'            => $data['city'],
                    // Full address in one line, exactly like the checkout does (invoice and courier read this)
                    'address'         => !empty($data['address_line'])
                        ? (string) $data['address_line']
                        : implode(', ', array_filter([$data['address'], $data['city'], $data['upazila'], $data['district']])),
                    'note'            => $data['note'] ?? null,
                    'subtotal'        => $subtotal,
                    'coupon_code'     => $couponCode,
                    'discount'        => $discount,
                    'delivery_charge' => $delivery,
                    'total'           => $subtotal - $discount + $delivery,
                    'status'          => 'pending',
                    'payment_method'  => 'cod',
                    'created_by_id'   => $byId,
                    'created_by_name' => $byName,
                    'source_order_id' => $data['source_order_id'] ?? null,
                    'address_id'      => $data['address_id'] ?? null,
                    'client_token'    => $token !== '' ? $token : null,
                    'order_type'      => $type,
                ] + ($claimId ? ['service_request_id' => $claimId] : []));

                $order->items()->createMany($rows);

                return $order;
            });
        } catch (QueryException $e) {
            // Two requests with the same token raced: the unique index let only one through
            if ($token !== '' && ($dup = Order::where('client_token', $token)->first())) {
                return [$dup, false];
            }
            throw $e;
        }

        // The customer record of this phone number (one per phone). A problem here must not undo the order.
        try {
            if (CustomerProfile::tableReady()) {
                CustomerProfile::findOrCreateByPhone($order->phone, $order->name, 'admin', $byId, $byName);
            }
        } catch (\Throwable $e) {
            report($e);
        }

        Notify::newOrder($order);
        foreach (Product::whereIn('id', array_keys($cart))->get() as $p) {
            Notify::lowStock($p);
        }

        Audit::log(
            'order.created', 'order', $order->id,
            ($isClaim ? ucfirst($type) . ' order' : 'Order') . ' #' . $order->id . ' created by ' . $byName . ' (৳ ' . number_format($order->total) . ')'
                . ($claimId ? ' from claim #' . $claimId : ''),
            null,
            [
                'items'           => count($cart),
                'subtotal'        => $order->subtotal,
                'discount'        => $order->discount,
                'delivery_charge' => $order->delivery_charge,
                'total'           => $order->total,
                'source_order_id' => $order->source_order_id,
                'order_type'      => $order->order_type,
                'service_request_id' => $claimId,
            ],
            $order->phone
        );

        return [$order, true];
    }
}
