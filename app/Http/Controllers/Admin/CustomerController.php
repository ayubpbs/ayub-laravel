<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerProfile;
use App\Models\Order;
use App\Models\ServiceRequest;
use App\Support\AdminAccess;
use App\Support\Audit;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Customers are found by phone number. Orders come from the orders table; admin-created profiles
 * (customer_profiles) and the old imported customers table are shown next to them.
 */
class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $q    = trim((string) $request->query('q', ''));
        $sort = (string) $request->query('sort', 'recent');

        $norm     = Phone::normalize($q);
        $fragment = Phone::fragment($q);
        $orderId  = preg_match('/^#?\s*(\d{1,9})$/', Phone::latinDigits($q), $m) ? (int) $m[1] : null;

        // A complete, valid number that we know goes straight to that customer
        if ($norm && !$request->query('list') && $this->phoneKnown($norm)) {
            return redirect()->route('admin.customers.show', $norm);
        }

        $query = Order::salesOrders()->select(     // replacement / exchange orders are not counted as customer orders
            'phone',
            DB::raw('count(*) as orders_count'),
            DB::raw("sum(case when status = 'delivered' then total else 0 end) as delivered_total"),
            DB::raw("sum(case when status in ('cancelled','returned') then 1 else 0 end) as failed_count"),
            DB::raw('max(created_at) as last_order_at'),
            DB::raw('max(id) as last_order_id')
        )->groupBy('phone');

        if ($q !== '') {
            // The matching phones are picked first, so each customer's totals still count ALL their orders
            $query->whereIn('phone', function ($sub) use ($q, $fragment, $orderId) {
                $sub->select('phone')->from('orders')->where(function ($w) use ($q, $fragment, $orderId) {
                    if ($fragment !== '') {
                        $w->orWhere('phone', 'like', "%{$fragment}%");
                    }
                    if ($orderId !== null) {
                        $w->orWhere('id', $orderId);
                    }
                    if (!str_starts_with($q, '#')) {
                        $w->orWhere('name', 'like', "%{$q}%");
                    }
                });
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
            'customers' => Order::salesOrders()->distinct()->count('phone'),
            'repeat'    => $this->salesOnly(DB::table('orders'))->select('phone')->groupBy('phone')->havingRaw('count(*) > 1')->get()->count(),
            'new_month' => $this->salesOnly(DB::table('orders'))->select('phone')->groupBy('phone')
                ->havingRaw('min(created_at) >= ?', [now()->startOfMonth()])->get()->count(),
        ];

        // People who are known but have no order in the list above (profiles added by hand, old site customers)
        $others = collect();
        if ($q !== '' && !$request->query('page')) {
            $others = $this->otherMatches($q, $fragment, $customers->pluck('phone')->all());
        }

        $canCreate = $q !== '' && $norm !== null && $others->isEmpty() && $customers->isEmpty();

        return view('admin.customers.index', compact('customers', 'latest', 'summary', 'q', 'sort', 'others', 'norm', 'canCreate'));
    }

    public function show($phone)
    {
        $norm = Phone::normalize($phone);
        abort_if($norm === null, 404);

        // One address per customer, whatever format was typed or stored
        if ((string) $phone !== $norm) {
            return redirect()->route('admin.customers.show', $norm);
        }

        $variants = Phone::variants($norm);
        $orders   = Order::withPaid()->with('items')->whereIn('phone', $variants)->latest()->get();
        $profile  = CustomerProfile::tableReady() ? CustomerProfile::where('phone', $norm)->first() : null;
        $imported = Customer::whereIn('phone', $variants)->first();

        abort_if($orders->isEmpty() && !$profile && !$imported, 404);

        $latest = $orders->first();
        $name   = $latest->name ?? $profile->name ?? $imported->name ?? $norm;

        // The numbers are real purchases: replacement / exchange orders are listed in the history but counted apart
        $sales = $orders->reject(fn ($o) => $o->isClaimOrder());
        $stats = [
            'orders'       => $sales->count(),
            'delivered'    => $sales->where('status', 'delivered')->count(),
            'failed'       => $sales->whereIn('status', Order::lockedStatuses())->count(),
            'spent'        => (int) $sales->where('status', 'delivered')->sum('total'),
            'claim_orders' => $orders->count() - $sales->count(),
            'first'     => $orders->isNotEmpty() ? $orders->last()->created_at : null,
        ];
        $names     = $orders->pluck('name')->push($profile->name ?? null)->push($imported->name ?? null)->filter()->unique()->values();
        $addresses = $orders->pluck('address')->unique()->values();

        $saved    = ($profile && CustomerAddress::tableReady()) ? $profile->addresses()->orderByDesc('is_default')->orderBy('id')->get() : collect();
        $activity = AuditLog::tableReady() ? AuditLog::where('customer_phone', $norm)->latest('id')->limit(15)->get() : collect();

        // Exchange, wrong-product and warranty claims of this customer (staff with "Claims: view" only)
        $claims = (ServiceRequest::tableReady() && AdminAccess::can('claims_view'))
            ? ServiceRequest::with('items')->whereIn('customer_phone', $variants)->latest('id')->limit(50)->get()
            : collect();

        return view('admin.customers.show', compact('orders', 'latest', 'stats', 'names', 'addresses', 'profile', 'imported', 'name', 'saved', 'activity', 'claims') + ['phone' => $norm]);
    }

    public function create(Request $request)
    {
        return view('admin.customers.create', ['phone' => (string) $request->query('phone', '')]);
    }

    public function store(Request $request)
    {
        if (!CustomerProfile::tableReady()) {
            return back()->withInput()->with('error', 'The customer profiles table is missing. Run "php artisan migrate" first.');
        }

        $data = $request->validate([
            'name'  => 'required|string|max:100',
            'phone' => 'required|string|max:30',
            'notes' => 'nullable|string|max:500',
        ], ['name.required' => 'Please enter the customer name.', 'phone.required' => 'Please enter the mobile number.']);

        $phone = Phone::normalize($data['phone']);
        if ($phone === null) {
            return back()->withInput()->with('error', 'Please enter a valid mobile number (e.g. 01712345678).');
        }

        // Already a customer by any route? Go to that customer instead of making a second record
        if ($this->phoneKnown($phone)) {
            return redirect()->route('admin.customers.show', $phone)->with('success', 'This customer already exists.');
        }

        $staff = AdminAccess::staff();
        [$profile, $created] = CustomerProfile::findOrCreateByPhone($phone, trim($data['name']), 'admin', $staff ? $staff->id : null, AdminAccess::name());

        if ($created && !empty($data['notes'])) {
            $profile->update(['notes' => $data['notes']]);
        }
        if ($created) {
            Audit::log('customer.created', 'customer_profile', $profile->id, 'Customer added: ' . $profile->name . ' (' . $phone . ')', null,
                ['name' => $profile->name, 'phone' => $phone, 'notes' => $data['notes'] ?? null], $phone);
        }

        return redirect()->route('admin.customers.show', $phone)
            ->with('success', $created ? 'Customer added.' : 'This customer already exists.');
    }

    // ----- Helpers -----

    /** Only real customer orders (no replacement / exchange orders) in a plain DB query */
    private function salesOnly($query)
    {
        return Order::hasAdminColumns()
            ? $query->where(fn ($w) => $w->whereNull('order_type')->orWhere('order_type', 'normal'))
            : $query;
    }

    /** True when the number has orders, a profile or an imported old-customer record */
    private function phoneKnown(string $norm): bool
    {
        return CustomerProfile::phoneKnown($norm);
    }

    /** Profiles and old customers that match the search but are not in the orders list */
    private function otherMatches(string $q, string $fragment, array $shownPhones)
    {
        $match = function ($query) use ($q, $fragment) {
            $query->where(function ($w) use ($q, $fragment) {
                if ($fragment !== '') {
                    $w->orWhere('phone', 'like', "%{$fragment}%");
                }
                if (!str_starts_with($q, '#')) {
                    $w->orWhere('name', 'like', "%{$q}%");
                }
            });
        };

        $out = collect();

        if (CustomerProfile::tableReady()) {
            foreach (CustomerProfile::where($match)->limit(10)->get() as $p) {
                $out->put($p->phone, ['phone' => $p->phone, 'name' => $p->name, 'label' => 'Profile (no orders yet)', 'orders' => 0]);
            }
        }
        foreach (Customer::where($match)->limit(10)->get() as $c) {
            $n = Phone::normalize($c->phone);
            if ($n !== null && !$out->has($n)) {
                $out->put($n, ['phone' => $n, 'name' => $c->name, 'label' => 'Old site customer', 'orders' => (int) $c->total_orders]);
            }
        }

        // Anyone who already has orders is in the main list
        $withOrders = Order::whereIn('phone', $out->keys()->all())->distinct()->pluck('phone')->all();

        return $out->reject(fn ($row, $phone) => in_array($phone, $withOrders, true) || in_array($phone, $shownPhones, true))->values();
    }
}
