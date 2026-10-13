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

        // Value of the goods on the shelf (null when the cost_price column does not exist yet)
        $stockSummary = null;
        try {
            if (\Illuminate\Support\Facades\Schema::hasColumn('products', 'cost_price')) {
                $stockSummary = Product::stockSummary();
            }
        } catch (\Throwable $e) {
            $stockSummary = null;
        }

        return view('admin.dashboard', compact('stats', 'recent', 'lowStock', 'stockSummary'));
    }
}
