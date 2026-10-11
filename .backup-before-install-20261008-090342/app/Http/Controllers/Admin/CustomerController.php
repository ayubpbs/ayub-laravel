<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Customers are guests, so there is no customers table: a customer is simply a mobile number
 * that has placed orders. Everything here is worked out from the orders table.
 */
class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $q    = trim((string) $request->query('q', ''));
        $sort = (string) $request->query('sort', 'recent');

        $query = Order::select(
            'phone',
            DB::raw('count(*) as orders_count'),
            DB::raw("sum(case when status = 'delivered' then total else 0 end) as delivered_total"),
            DB::raw("sum(case when status in ('cancelled','returned') then 1 else 0 end) as failed_count"),
            DB::raw('max(created_at) as last_order_at'),
            DB::raw('max(id) as last_order_id')
        )->groupBy('phone');

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('phone', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%");
            });
        }

        if ($sort === 'orders') {
            $query->orderByDesc('orders_count')->orderByDesc('last_order_at');
        } elseif ($sort === 'spent') {
            $query->orderByDesc('delivered_total')->orderByDesc('last_order_at');
        } else {
            $sort = 'recent';
            $query->orderByDesc('last_order_at');
        }

        $customers = $query->paginate(20)->withQueryString();

        // Name and address come from each customer's latest order
        $latest = Order::whereIn('id', $customers->pluck('last_order_id'))->get()->keyBy('id');

        $summary = [
            'customers' => Order::distinct()->count('phone'),
            'repeat'    => DB::table('orders')->select('phone')->groupBy('phone')->havingRaw('count(*) > 1')->get()->count(),
            'new_month' => DB::table('orders')->select('phone')->groupBy('phone')
                ->havingRaw('min(created_at) >= ?', [now()->startOfMonth()])->get()->count(),
        ];

        return view('admin.customers.index', compact('customers', 'latest', 'summary', 'q', 'sort'));
    }

    public function show($phone)
    {
        $orders = Order::where('phone', $phone)->latest()->get();
        abort_if($orders->isEmpty(), 404);

        $latest = $orders->first();

        $stats = [
            'orders'    => $orders->count(),
            'delivered' => $orders->where('status', 'delivered')->count(),
            'failed'    => $orders->whereIn('status', Order::lockedStatuses())->count(),
            'spent'     => (int) $orders->where('status', 'delivered')->sum('total'),
            'first'     => $orders->last()->created_at,
        ];
        $names     = $orders->pluck('name')->unique()->values();
        $addresses = $orders->pluck('address')->unique()->values();

        return view('admin.customers.show', compact('orders', 'latest', 'stats', 'names', 'addresses', 'phone'));
    }
}
