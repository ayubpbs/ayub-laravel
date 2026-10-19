<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerAddress;
use App\Models\CustomerProfile;
use App\Models\Order;
use App\Models\Product;
use App\Services\AdminOrderBuilder;
use App\Support\AdminAccess;
use App\Support\Audit;
use App\Support\BdLocations;
use App\Support\Cart;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Admin: create an order for a customer (phone order, walk-in) and Reorder from an old order.
 * Everything sits behind the admin login and the "orders_create" permission.
 */
class OrderCreateController extends Controller
{
    public function create(Request $request)
    {
        $form    = ['name' => '', 'phone' => '', 'district' => '', 'upazila' => '', 'city' => '', 'address' => '', 'note' => '', 'coupon_code' => ''];
        $items   = [];
        $skipped = [];
        $source  = null;

        if ($request->query('reorder') !== null) {
            $source = Order::with('items')->findOrFail((int) $request->query('reorder'));

            $form = [
                'name' => $source->name, 'phone' => $source->phone, 'district' => (string) $source->district,
                'upazila' => (string) $source->upazila, 'city' => (string) $source->city,
                'address' => $this->street($source), 'note' => '', 'coupon_code' => '',
            ];

            // Current price and stock are always used; the old order is only a list of products and quantities
            $products = Product::whereIn('id', $source->items->pluck('product_id')->filter()->all())->get()->keyBy('id');
            foreach ($source->items as $it) {
                $p = $it->product_id ? $products->get($it->product_id) : null;

                if (!$p || !$p->is_published || $p->is_upcoming) {
                    $skipped[] = '"' . $it->title . '" is not available any more, so it was left out.';
                } elseif ((int) $p->stock_quantity <= 0) {
                    $skipped[] = '"' . $it->title . '" is out of stock, so it was left out.';
                } else {
                    $qty  = min((int) $it->quantity, (int) $p->stock_quantity);
                    $note = null;
                    if ($qty < (int) $it->quantity) {
                        $note = 'Only ' . (int) $p->stock_quantity . ' in stock (the old order had ' . (int) $it->quantity . ').';
                    } elseif ((int) $p->price !== (int) $it->price) {
                        $note = 'Price changed: was ৳ ' . number_format($it->price) . ', now ৳ ' . number_format($p->price) . '.';
                    }
                    $items[] = $this->itemRow($p, $qty, $note);
                }
            }
        } elseif ($request->query('phone')) {
            $phone = Phone::normalize((string) $request->query('phone'));
            if ($phone) {
                $form['phone'] = $phone;
                $latest = Order::whereIn('phone', Phone::variants($phone))->latest('id')->first();
                $form['name'] = $latest->name ?? (CustomerProfile::tableReady() ? (string) CustomerProfile::where('phone', $phone)->value('name') : '');
            }
        }

        // After a validation error the form comes back with what the admin had typed
        if (is_array($request->old('items'))) {
            $form  = array_merge($form, array_intersect_key($request->old(), $form));
            $items = $this->itemsFromInput($request->old('items'));
        }

        return view('admin.orders.create', [
            'form'      => $form,
            'items'     => $items,
            'skipped'   => $skipped,
            'source'    => $source,
            'token'     => Str::random(40),
            'locations' => array_map(fn ($d) => ['n' => $d['n'], 'b' => $d['b'], 'u' => $d['u']], BdLocations::all()),
            'rates'     => Cart::rates(),
            'ready'     => Order::hasAdminColumns(),
        ]);
    }

    /** JSON: saved addresses and last used name for a phone number (fills the form) */
    public function lookup(Request $request)
    {
        $phone = Phone::normalize((string) $request->query('phone'));
        if (!$phone) {
            return response()->json(['valid' => false]);
        }

        $latest = Order::whereIn('phone', Phone::variants($phone))->latest('id')->first();
        $name   = $latest->name ?? '';
        $addrs  = [];

        if (CustomerProfile::tableReady() && CustomerAddress::tableReady()) {
            $profile = CustomerProfile::where('phone', $phone)->first();
            if ($profile) {
                $name = $name ?: (string) $profile->name;
                foreach (CustomerAddress::where('customer_profile_id', $profile->id)->orderByDesc('is_default')->orderByDesc('id')->get() as $a) {
                    $addrs[] = [
                        'id' => $a->id, 'recipient_name' => $a->recipient_name, 'district' => $a->district, 'upazila' => $a->upazila,
                        'city' => $a->city, 'address' => $a->address, 'landmark' => $a->landmark, 'is_default' => $a->is_default, 'line' => $a->fullLine(),
                    ];
                }
            }
        }

        return response()->json([
            'valid'     => true,
            'phone'     => $phone,
            'known'     => (bool) $latest || $addrs !== [] || CustomerProfile::phoneKnown($phone),
            'name'      => $name,
            'addresses' => $addrs,
            'last'      => $latest ? [
                'district' => (string) $latest->district, 'upazila' => (string) $latest->upazila,
                'city' => (string) $latest->city, 'address' => $this->street($latest), 'line' => $latest->address,
            ] : null,
        ]);
    }

    /** JSON: product search for the order form */
    public function products(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $q) . '%';

        return response()->json(
            Product::published()->where('is_upcoming', 0)
                ->where(function ($w) use ($like) {
                    $w->where('title', 'like', $like)->orWhere('sku', 'like', $like);
                })
                ->orderByDesc('stock_quantity')->limit(15)->get()
                ->map(fn ($p) => ['id' => $p->id, 'title' => $p->title, 'sku' => $p->sku, 'price' => (int) $p->price, 'stock' => (int) $p->stock_quantity])
                ->values()
        );
    }

    public function store(Request $request, AdminOrderBuilder $builder)
    {
        $request->merge(['phone' => Phone::normalize((string) $request->input('phone')) ?? (string) $request->input('phone')]);

        $data = $request->validate([
            'name'           => 'required|string|max:100',
            'phone'          => ['required', 'regex:/^01[3-9]\d{8}$/'],
            'district'       => ['required', Rule::in(BdLocations::districtNames())],
            'upazila'        => 'required|string|max:100',
            'city'           => 'required|string|max:100',
            'address'        => 'required|string|max:500',
            'note'           => 'nullable|string|max:500',
            'coupon_code'    => 'nullable|string|max:30',
            'client_token'   => 'required|string|max:64',
            'source_order_id' => 'nullable|integer',
            'address_id'     => 'nullable|integer',
            'save_address'   => 'nullable|boolean',
            'items'          => 'required|array|min:1|max:50',
            'items.*.product_id' => 'required|integer|min:1',
            'items.*.quantity'   => 'required|integer|min:1|max:999',
        ], [
            'name.required'     => 'Please enter the customer name.',
            'phone.required'    => 'Please enter the mobile number.',
            'phone.regex'       => 'Please enter a valid mobile number (e.g. 01712345678).',
            'district.required' => 'Please select the district.',
            'district.in'       => 'Please select a valid district.',
            'upazila.required'  => 'Please select the upazila.',
            'city.required'     => 'Please enter the city or town.',
            'address.required'  => 'Please enter the delivery address.',
            'items.required'    => 'Please add at least one product.',
            'items.min'         => 'Please add at least one product.',
        ]);

        if (!BdLocations::hasUpazila($data['district'], $data['upazila'])) {
            throw ValidationException::withMessages(['upazila' => 'Please select a valid upazila for the chosen district.']);
        }

        // Links are only kept when they are real: a made-up id is simply ignored
        $data['source_order_id'] = !empty($data['source_order_id']) && Order::whereKey($data['source_order_id'])->exists() ? (int) $data['source_order_id'] : null;
        $data['address_id']      = $this->ownAddressId($data['phone'], $data['address_id'] ?? null);

        $lines = [];
        foreach ($data['items'] as $row) {
            $lines[(int) $row['product_id']] = ($lines[(int) $row['product_id']] ?? 0) + (int) $row['quantity'];
        }

        try {
            [$order, $created] = $builder->create($data, $lines);
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', 'The coupon could not be used: ' . $e->getMessage());
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        if (!$created) {
            return redirect()->route('admin.orders.show', $order->id)->with('success', 'This order was already created (the form was sent twice), so no second order was made.');
        }

        $saved = $request->boolean('save_address') && !$order->address_id && $this->saveAddress($order, $data);

        return redirect()->route('admin.orders.show', $order->id)
            ->with('success', 'Order #' . $order->id . ' created.' . ($saved ? ' The address was saved for this customer.' : ''));
    }

    // ----- Helpers -----

    private function itemRow(Product $p, int $qty, ?string $note = null): array
    {
        return ['id' => $p->id, 'title' => $p->title, 'sku' => $p->sku, 'price' => (int) $p->price, 'stock' => (int) $p->stock_quantity, 'qty' => $qty, 'note' => $note];
    }

    /** Rebuilds the product rows from submitted input (after a validation error) using CURRENT price and stock */
    private function itemsFromInput(array $input): array
    {
        $qtys = [];
        foreach ($input as $row) {
            $id = (int) ($row['product_id'] ?? 0);
            if ($id > 0) {
                $qtys[$id] = ($qtys[$id] ?? 0) + max(1, (int) ($row['quantity'] ?? 1));
            }
        }

        $out = [];
        foreach (Product::whereIn('id', array_keys($qtys))->get() as $p) {
            $out[] = $this->itemRow($p, $qtys[$p->id]);
        }

        return $out;
    }

    /** The street part of an order's address (orders.address also holds city, upazila and district) */
    private function street(Order $o): string
    {
        $tail = implode(', ', array_filter([$o->city, $o->upazila, $o->district]));
        $addr = (string) $o->address;

        return ($tail !== '' && str_ends_with($addr, ', ' . $tail)) ? substr($addr, 0, -strlen(', ' . $tail)) : $addr;
    }

    /** A saved-address id is only kept when it belongs to the customer with this phone */
    private function ownAddressId(string $phone, $id): ?int
    {
        if (!$id || !CustomerProfile::tableReady() || !CustomerAddress::tableReady()) {
            return null;
        }

        $profile = CustomerProfile::where('phone', $phone)->first();
        if (!$profile) {
            return null;
        }

        return CustomerAddress::where('customer_profile_id', $profile->id)->whereKey((int) $id)->value('id');
    }

    /** Saves the order's address on the customer, unless it is already there or the limit is reached. True if saved. */
    private function saveAddress(Order $order, array $data): bool
    {
        if (!CustomerProfile::tableReady() || !CustomerAddress::tableReady()) {
            return false;
        }

        try {
            $address = DB::transaction(function () use ($order, $data) {
                $staff = AdminAccess::staff();
                [$profile] = CustomerProfile::findOrCreateByPhone($order->phone, $order->name, 'admin', $staff->id ?? null, AdminAccess::name());

                $profile  = CustomerProfile::whereKey($profile->id)->lockForUpdate()->first();
                $existing = CustomerAddress::where('customer_profile_id', $profile->id)->get();

                if ($existing->count() >= CustomerAddress::MAX_PER_CUSTOMER) {
                    return null;
                }

                $fields = [
                    'recipient_name' => $order->name, 'phone' => $order->phone, 'district' => $data['district'],
                    'upazila' => $data['upazila'], 'city' => $data['city'], 'address' => $data['address'],
                ];
                $key = CustomerAddress::duplicateKey($fields);
                foreach ($existing as $e) {
                    if (CustomerAddress::duplicateKey($e->toArray()) === $key) {
                        return null; // already saved
                    }
                }

                return CustomerAddress::create(['customer_profile_id' => $profile->id, 'is_default' => $existing->isEmpty()] + $fields);
            });
        } catch (\Throwable $e) {
            report($e);

            return false;
        }

        if (!$address) {
            return false;
        }

        Audit::log('address.created', 'customer_address', $address->id, 'Address saved with order #' . $order->id . ': ' . $address->fullLine(), null, $address->snapshot(), $order->phone);

        return true;
    }
}
