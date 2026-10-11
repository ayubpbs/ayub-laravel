<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Support\Steadfast;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CourierController extends Controller
{
    // ---------- Steadfast: connection + sending ----------

    public function steadfast()
    {
        $configured = Steadfast::configured();

        $balance = null;
        if ($configured) {
            $balance = Cache::remember('steadfast_balance', 60, function () {
                $r = Steadfast::balance();
                return $r['ok'] ? ['ok' => true, 'amount' => $r['data']['current_balance'] ?? null] : ['ok' => false, 'message' => $r['message']];
            });
            if (!($balance['ok'] ?? false)) {
                Cache::forget('steadfast_balance'); // do not remember failures
            }
        }

        $ready  = Order::with('items')->where('status', 'confirmed')->whereNull('consignment_id')->oldest()->get();
        $recent = Order::whereNotNull('consignment_id')->latest('courier_sent_at')->limit(10)->get();

        return view('admin.courier.steadfast', compact('configured', 'balance', 'ready', 'recent'));
    }

    public function send($id)
    {
        $result = $this->sendOne((int) $id);

        return back()->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    public function sendBulk(Request $request)
    {
        $ids = array_slice(array_unique(array_map('intval', (array) $request->input('ids', []))), 0, 20);
        if (!$ids) {
            return back()->with('error', 'Please tick at least one order.');
        }

        $sent = 0;
        $fail = [];
        foreach ($ids as $id) {
            $r = $this->sendOne($id);
            $r['ok'] ? $sent++ : $fail[] = '#' . $id . ': ' . $r['message'];
        }

        $msg = $sent . ' order(s) sent to Steadfast.';
        if ($fail) {
            return back()->with($sent ? 'success' : 'error', $msg . ($sent ? ' ' : '') . 'Failed — ' . implode(' | ', $fail));
        }

        return back()->with('success', $msg);
    }

    /** Books one order. A row lock makes sure a double click can never create two parcels. */
    private function sendOne(int $id): array
    {
        return DB::transaction(function () use ($id) {
            $order = Order::with('items')->lockForUpdate()->find($id);

            if (!$order) {
                return ['ok' => false, 'message' => 'Order not found.'];
            }
            if ($order->consignment_id) {
                return ['ok' => false, 'message' => 'Order #' . $id . ' was already sent to Steadfast.'];
            }
            if ($order->status !== 'confirmed') {
                return ['ok' => false, 'message' => 'Order #' . $id . ' must be "Confirmed" before it is sent to the courier.'];
            }

            $res = Steadfast::createOrder($order);
            $c   = $res['data']['consignment'] ?? null;

            if (!$res['ok'] || !is_array($c) || empty($c['consignment_id'])) {
                return ['ok' => false, 'message' => 'Order #' . $id . ': ' . ($res['message'] ?: 'Steadfast did not return a consignment.')];
            }

            $order->update([
                'courier'         => 'steadfast',
                'consignment_id'  => (string) $c['consignment_id'],
                'tracking_code'   => $c['tracking_code'] ?? null,
                'courier_status'  => $c['status'] ?? 'in_review',
                'courier_sent_at' => now(),
            ]);
            $order->changeStatus('courier_sent');

            Cache::forget('steadfast_balance');

            return ['ok' => true, 'message' => 'Order #' . $id . ' sent to Steadfast (consignment ' . $c['consignment_id'] . ').'];
        });
    }

    // ---------- Tracking ----------

    public function tracking(Request $request)
    {
        $q  = trim((string) $request->query('q', ''));
        $st = (string) $request->query('cs', '');

        $query = Order::whereNotNull('consignment_id')->latest('courier_sent_at');

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('consignment_id', 'like', "%{$q}%")
                  ->orWhere('tracking_code', 'like', "%{$q}%")
                  ->orWhere('phone', 'like', "%{$q}%")
                  ->orWhere('name', 'like', "%{$q}%")
                  ->orWhere('id', ltrim($q, '#'));
            });
        }
        if ($st !== '' && array_key_exists($st, Steadfast::statuses())) {
            $query->where('courier_status', $st);
        } else {
            $st = '';
        }

        $orders = $query->paginate(20)->withQueryString();

        $activeCount = Order::whereNotNull('consignment_id')->where('status', 'courier_sent')
            ->where(fn ($w) => $w->whereNull('courier_status')->orWhereNotIn('courier_status', Steadfast::FINAL))->count();

        return view('admin.courier.tracking', [
            'orders' => $orders, 'q' => $q, 'cs' => $st, 'activeCount' => $activeCount,
            'configured' => Steadfast::configured(),
        ]);
    }

    public function refresh($id)
    {
        $order = Order::findOrFail($id);
        $r = $this->refreshOne($order);

        return back()->with($r['ok'] ? 'success' : 'error', $r['message']);
    }

    public function refreshAll()
    {
        // At most 25 per click so the page never waits too long
        $orders = Order::whereNotNull('consignment_id')->where('status', 'courier_sent')
            ->where(fn ($w) => $w->whereNull('courier_status')->orWhereNotIn('courier_status', Steadfast::FINAL))
            ->oldest('courier_checked_at')->limit(25)->get();

        if ($orders->isEmpty()) {
            return back()->with('success', 'Nothing to refresh — every parcel is already final.');
        }

        $ok = 0; $delivered = 0; $last = null;
        foreach ($orders as $o) {
            $r = $this->refreshOne($o);
            if ($r['ok']) { $ok++; $r['delivered'] && $delivered++; } else { $last = $r['message']; }
        }

        $msg = "Checked {$ok} of {$orders->count()} parcel(s)" . ($delivered ? ", {$delivered} now marked Delivered" : '') . '.';

        return back()->with($last && !$ok ? 'error' : 'success', $msg . ($last ? ' ' . $last : ''));
    }

    private function refreshOne(Order $order): array
    {
        if (!$order->consignment_id) {
            return ['ok' => false, 'delivered' => false, 'message' => 'Order #' . $order->id . ' has no consignment.'];
        }

        $res = Steadfast::status($order->consignment_id);
        $new = $res['data']['delivery_status'] ?? null;

        if (!$res['ok'] || !$new) {
            return ['ok' => false, 'delivered' => false, 'message' => 'Order #' . $order->id . ': ' . ($res['message'] ?: 'no status returned.')];
        }

        $previous = $order->courier_status;
        $order->update(['courier_status' => $new, 'courier_checked_at' => now()]);

        // The courier cancelled the parcel: it is coming back to the shop
        if (in_array($new, ['cancelled', 'cancelled_approval_pending'], true) && $previous !== $new && !$order->isLocked()) {
            \App\Support\Notify::courierReturning($order);
        }

        // Only a confirmed delivery is applied to the order automatically
        $delivered = false;
        if ($new === 'delivered' && $order->status === 'courier_sent') {
            $order->load('items');
            $order->changeStatus('delivered');
            $delivered = true;
        }

        return ['ok' => true, 'delivered' => $delivered, 'message' => 'Order #' . $order->id . ': ' . Steadfast::label($new) . ($delivered ? ' — order marked Delivered.' : '.')];
    }

    // ---------- Returns ----------

    public function returns()
    {
        // Parcels Steadfast cancelled (the customer did not accept it): they are coming back to the shop
        $attention = Order::whereNotNull('consignment_id')
            ->whereIn('courier_status', ['cancelled', 'cancelled_approval_pending'])
            ->whereNotIn('status', Order::lockedStatuses())
            ->latest('courier_checked_at')->get();

        $returned = Order::where('status', 'returned')->latest('updated_at')->limit(20)->get();

        $remote = null;
        if (Steadfast::configured()) {
            $remote = Steadfast::returnRequests();
        }

        return view('admin.courier.returns', compact('attention', 'returned', 'remote'));
    }

    public function requestReturn($id)
    {
        $order = Order::findOrFail($id);

        if (!$order->consignment_id) {
            return back()->with('error', 'This order has no Steadfast consignment.');
        }
        if ($order->return_requested_at) {
            return back()->with('error', 'A return was already requested for order #' . $order->id . '.');
        }

        $res = Steadfast::createReturn($order->consignment_id, 'Order #' . $order->id . ' — return to merchant');
        if (!$res['ok']) {
            return back()->with('error', 'Order #' . $order->id . ': ' . $res['message']);
        }

        $order->update(['return_requested_at' => now()]);

        return back()->with('success', 'Return requested from Steadfast for order #' . $order->id . '.');
    }

    public function markReturned($id)
    {
        $order = Order::with('items')->findOrFail($id);

        if ($order->isLocked()) {
            return back()->with('error', 'Order #' . $order->id . ' is already ' . strtolower($order->statusLabel()) . '.');
        }

        $order->changeStatus('returned');

        return back()->with('success', 'Order #' . $order->id . ' marked Returned and its products are back in stock.');
    }
}
