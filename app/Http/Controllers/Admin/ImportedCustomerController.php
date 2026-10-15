<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;

class ImportedCustomerController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $sort = $request->query('sort', 'recent');

        $query = Customer::query()
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($w) use ($q) {
                    $w->where('name', 'like', "%{$q}%")
                      ->orWhere('phone', 'like', "%{$q}%")
                      ->orWhere('address', 'like', "%{$q}%");
                });
            });

        if ($sort === 'orders') {
            $query->orderByDesc('total_orders')->orderByDesc('last_order');
        } else {
            $sort = 'recent';
            $query->orderByDesc('last_order');
        }

        $customers = $query->paginate(25)->withQueryString();

        $summary = [
            'customers' => Customer::count(),
            'repeat'    => Customer::where('total_orders', '>', 1)->count(),
        ];

        return view('admin.customers.imported', compact('customers', 'q', 'sort', 'summary'));
    }
}
