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
        $query  = Order::withPaid()->latest();

        if ($status !== '' && array_key_exists($status, Order::statuses())) {
            $query->where('status', $status);
        } else {
            $status = '';
        }

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('id', ltrim($search, '#'));
            });
        }

        $orders = $query->paginate(20)->withQueryString();
        $counts = Order::select('status', DB::raw('count(*) as c'))->groupBy('status')->pluck('c', 'status');

        return view('admin.orders.index', compact('orders', 'counts', 'status', 'search'));
    }

    public function show($id)
    {
        $order = Order::with(['items', 'payments', 'invoice'])->findOrFail($id);

        return view('admin.orders.show', compact('order'));
    }

    public function status(Request $request, $id)
    {
        $order = Order::with('items')->findOrFail($id);

        $new = $request->validate([
            'status' => 'required|in:' . implode(',', array_keys(Order::statuses())),
        ])['status'];

        if ($order->isLocked()) {
            return back()->with('error', 'The status of a ' . strtolower($order->statusLabel()) . ' order can no longer be changed.');
        }
        if ($new === $order->status) {
            return back();
        }

        $order->changeStatus($new);

        return back()->with('success', 'Order status changed to "' . Order::statuses()[$new] . '".');
    }
}
