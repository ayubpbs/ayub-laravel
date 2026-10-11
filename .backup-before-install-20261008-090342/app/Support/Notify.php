<?php

namespace App\Support;

use App\Models\AdminNotification;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Creates the admin notifications. Every method is failure-safe:
 * a problem here must never stop an order, payment or status change.
 */
class Notify
{
    public static function threshold(): int
    {
        return (int) config('shop.low_stock_threshold', 5);
    }

    private static function push(string $type, string $title, ?string $message = null, array $extra = []): ?AdminNotification
    {
        try {
            return AdminNotification::create(['type' => $type, 'title' => $title, 'message' => $message] + $extra);
        } catch (Throwable $e) {
            Log::warning('Notification not saved: ' . $e->getMessage());
            return null;
        }
    }

    private static function orderUrl(Order $o): string
    {
        return route('admin.orders.show', $o->id, false);
    }

    private static function who(Order $o): string
    {
        return $o->name . ' (' . $o->phone . ')';
    }

    public static function newOrder(Order $o): void
    {
        self::push('new_order', 'New order #' . $o->id,
            self::who($o) . ' — ৳ ' . number_format($o->total),
            ['order_id' => $o->id, 'url' => self::orderUrl($o)]);
    }

    /** Called from Order::changeStatus() */
    public static function orderStatus(Order $o, string $status): void
    {
        $map = [
            'confirmed'    => ['order_confirmed', 'Order #' . $o->id . ' confirmed'],
            'courier_sent' => ['courier_sent',    'Order #' . $o->id . ' sent to courier'],
            'delivered'    => ['delivered',       'Order #' . $o->id . ' delivered'],
            'returned'     => ['return',          'Order #' . $o->id . ' returned'],
        ];
        if (!isset($map[$status])) {
            return; // pending / cancelled do not notify
        }

        [$type, $title] = $map[$status];
        $msg = self::who($o) . ' — ৳ ' . number_format($o->total);
        if ($status === 'courier_sent' && $o->consignment_id) {
            $msg .= ' — consignment ' . $o->consignment_id;
        }
        if ($status === 'returned') {
            $msg .= ' — products are back in stock';
        }

        self::push($type, $title, $msg, ['order_id' => $o->id, 'url' => self::orderUrl($o)]);
    }

    /** The courier cancelled the parcel, so it is on its way back to the shop */
    public static function courierReturning(Order $o): void
    {
        self::push('return', 'Parcel of order #' . $o->id . ' is coming back',
            self::who($o) . ' — courier status: ' . str_replace('_', ' ', (string) $o->courier_status),
            ['order_id' => $o->id, 'url' => route('admin.courier.returns', [], false)]);
    }

    /** One open alert per product: updates it instead of piling up duplicates */
    public static function lowStock(Product $p): void
    {
        if ($p->is_upcoming || (int) $p->stock_quantity > self::threshold()) {
            return;
        }

        $out   = (int) $p->stock_quantity < 1;
        $title = ($out ? 'Out of stock: ' : 'Low stock: ') . $p->title;
        $msg   = $out ? 'No units left.' : 'Only ' . $p->stock_quantity . ' left (alert at ' . self::threshold() . ' or fewer).';
        $url   = route('admin.products.edit', $p->id, false);

        try {
            $open = AdminNotification::unread()->where('type', 'low_stock')->where('product_id', $p->id)->first();
            if ($open) {
                $open->update(['title' => $title, 'message' => $msg, 'created_at' => now()]);
                return;
            }
        } catch (Throwable $e) {
            return;
        }

        self::push('low_stock', $title, $msg, ['product_id' => $p->id, 'url' => $url]);
    }

    /** Stock is back above the limit: the old alert is no longer needed */
    public static function stockRecovered(Product $p): void
    {
        if ((int) $p->stock_quantity <= self::threshold()) {
            return;
        }
        try {
            AdminNotification::unread()->where('type', 'low_stock')->where('product_id', $p->id)->update(['read_at' => now()]);
        } catch (Throwable $e) {
            // ignore
        }
    }

    public static function payment(Payment $pay): void
    {
        $refund = $pay->isRefund();
        $order  = $pay->order;

        self::push('payment',
            ($refund ? 'Refund' : 'Payment received') . ': ৳ ' . number_format($pay->amount) . ' — order #' . $pay->order_id,
            $pay->methodLabel() . ($pay->trx_id ? ' · TrxID ' . $pay->trx_id : '') . ($order ? ' · ' . $order->name : ''),
            ['order_id' => $pay->order_id, 'url' => route('admin.orders.show', $pay->order_id, false)]);
    }
}
