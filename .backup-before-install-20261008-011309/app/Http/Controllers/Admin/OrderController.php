<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $status = (string) $request->query('status', '');
        $query  = Order::latest();

        if ($status !== '' && array_key_exists($status, Order::statuses())) {
            $query->where('status', $status);
        } else {
            $status = '';
        }

        $orders = $query->paginate(20)->withQueryString();
        $counts = Order::select('status', DB::raw('count(*) as c'))->groupBy('status')->pluck('c', 'status');

        return view('admin.orders.index', compact('orders', 'counts', 'status'));
    }

    public function show($id)
    {
        $order = Order::with('items')->findOrFail($id);

        return view('admin.orders.show', compact('order'));
    }

    public function status(Request $request, $id)
    {
        $order = Order::with('items')->findOrFail($id);

        $new = $request->validate([
            'status' => 'required|in:' . implode(',', array_keys(Order::statuses())),
        ])['status'];

        if ($order->status === 'cancelled') {
            return back()->with('error', 'The status of a cancelled order can no longer be changed.');
        }
        if ($new === $order->status) {
            return back();
        }

        DB::transaction(function () use ($order, $new) {
            // If the order is cancelled, the products go back into stock
            if ($new === 'cancelled') {
                foreach ($order->items as $item) {
                    if ($item->product_id) {
                        Product::where('id', $item->product_id)->increment('stock_quantity', $item->quantity);
                    }
                }
            }
            $order->update(['status' => $new]);
        });

        return back()->with('success', 'Order status changed to "' . Order::statuses()[$new] . '".');
    }
}
