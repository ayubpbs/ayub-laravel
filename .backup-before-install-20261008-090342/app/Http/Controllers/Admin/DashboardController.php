<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'pending'   => Order::where('status', 'pending')->count(),
            'today'     => Order::whereDate('created_at', today())->count(),
            'orders'    => Order::count(),
            'delivered' => (int) Order::where('status', 'delivered')->sum('total'),
            'products'  => Product::count(),
        ];

        $recent   = Order::latest()->limit(8)->get();
        $lowStock = Product::where('is_upcoming', 0)->where('stock_quantity', '<=', 5)
            ->orderBy('stock_quantity')->limit(8)->get();

        return view('admin.dashboard', compact('stats', 'recent', 'lowStock'));
    }
}
