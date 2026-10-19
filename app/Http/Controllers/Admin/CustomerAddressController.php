<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomerAddress;
use App\Models\CustomerProfile;
use App\Models\Order;
use App\Support\Audit;
use App\Support\BdLocations;
use App\Support\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Saved delivery addresses of a customer (admin only). An address is always looked up THROUGH the
 * customer in the URL, so an address id of another customer can never be opened or changed.
 * Orders keep their own copy of the address, so nothing here can change an existing order.
 */
class CustomerAddressController extends Controller
{
    public function create($phone)
    {
        $norm   = $this->knownPhone($phone);
        $latest = Order::whereIn('phone', Phone::variants($norm))->latest('id')->first();

        $address = new CustomerAddress([
            'recipient_name' => $latest->name ?? CustomerProfile::where('phone', $norm)->value('name'),
            'phone'          => $norm,
            'is_default'     => false,
        ]);

        return view('admin.customers.addresses.form', $this->formData($norm, $address));
    }

    public function store(Request $request, $phone)
    {
        $norm = $this->knownPhone($phone);

        if (!CustomerProfile::tableReady() || !CustomerAddress::tableReady()) {
            return back()->withInput()->with('error', 'The address tables are missing. Run "php artisan migrate" first.');
        }

        $data = $this->validated($request);

        try {
            $address = DB::transaction(function () use ($norm, $data) {
                $latest = Order::whereIn('phone', Phone::variants($norm))->latest('id')->first();
                [$profile] = CustomerProfile::findOrCreateByPhone(
                    $norm, $latest->name ?? $data['recipient_name'], $latest ? 'orders' : 'admin',
                    \App\Support\AdminAccess::staff()->id ?? null, \App\Support\AdminAccess::name()
                );

                // Lock the customer row: two admins saving at once cannot create two defaults or skip the limit
                $profile  = CustomerProfile::whereKey($profile->id)->lockForUpdate()->first();
                $existing = CustomerAddress::where('customer_profile_id', $profile->id)->get();

                if ($existing->count() >= CustomerAddress::MAX_PER_CUSTOMER) {
                    throw new \DomainException('A customer can have at most ' . CustomerAddress::MAX_PER_CUSTOMER . ' saved addresses. Delete one first.');
                }
                $this->refuseDuplicate($existing, $data, null);

                $default = $data['is_default'] || $existing->isEmpty();
                if ($default) {
                    CustomerAddress::where('customer_profile_id', $profile->id)->update(['is_default' => false]);
                }

                return CustomerAddress::create(['customer_profile_id' => $profile->id, 'is_default' => $default] + $this->fields($data));
            });
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        Audit::log('address.created', 'customer_address', $address->id, 'Address saved: ' . $address->fullLine(), null, $address->snapshot(), $norm);

        return redirect()->route('admin.customers.show', $norm)->with('success', 'Address saved.');
    }

    public function edit($phone, $id)
    {
        $norm = $this->knownPhone($phone);

        return view('admin.customers.addresses.form', $this->formData($norm, $this->owned($norm, $id)));
    }

    public function update(Request $request, $phone, $id)
    {
        $norm = $this->knownPhone($phone);
        $this->owned($norm, $id); // 404 unless this address belongs to this customer
        $data = $this->validated($request);

        try {
            [$old, $address] = DB::transaction(function () use ($norm, $id, $data) {
                $profile = CustomerProfile::where('phone', $norm)->lockForUpdate()->firstOrFail();
                $address = CustomerAddress::where('customer_profile_id', $profile->id)->lockForUpdate()->findOrFail($id);
                $others  = CustomerAddress::where('customer_profile_id', $profile->id)->where('id', '<>', $address->id)->get();

                $this->refuseDuplicate($others, $data, $address->id);

                $old = $address->snapshot();

                // The default can only move by making another address the default
                $default = $address->is_default || $data['is_default'];
                if ($default && !$address->is_default) {
                    CustomerAddress::where('customer_profile_id', $profile->id)->update(['is_default' => false]);
                }

                $address->update($this->fields($data) + ['is_default' => $default]);

                return [$old, $address->fresh()];
            });
        } catch (\DomainException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        Audit::log('address.updated', 'customer_address', $address->id, 'Address changed: ' . $address->fullLine(), $old, $address->snapshot(), $norm);

        return redirect()->route('admin.customers.show', $norm)->with('success', 'Address updated. Existing orders were not changed.');
    }

    public function makeDefault($phone, $id)
    {
        $norm = $this->knownPhone($phone);
        $this->owned($norm, $id);

        $address = DB::transaction(function () use ($norm, $id) {
            $profile = CustomerProfile::where('phone', $norm)->lockForUpdate()->firstOrFail();
            CustomerAddress::where('customer_profile_id', $profile->id)->update(['is_default' => false]);
            $address = CustomerAddress::where('customer_profile_id', $profile->id)->findOrFail($id);
            $address->update(['is_default' => true]);

            return $address;
        });

        Audit::log('address.default', 'customer_address', $address->id, 'Default address set: ' . $address->fullLine(), null, null, $norm);

        return back()->with('success', 'Default address changed.');
    }

    public function destroy($phone, $id)
    {
        $norm = $this->knownPhone($phone);
        $this->owned($norm, $id);

        $old = DB::transaction(function () use ($norm, $id) {
            $profile = CustomerProfile::where('phone', $norm)->lockForUpdate()->firstOrFail();
            $address = CustomerAddress::where('customer_profile_id', $profile->id)->findOrFail($id);
            $old     = $address->snapshot();
            $wasDef  = $address->is_default;
            $address->delete();

            // Never leave a customer with addresses but no default
            if ($wasDef) {
                $next = CustomerAddress::where('customer_profile_id', $profile->id)->orderByDesc('id')->first();
                if ($next) {
                    $next->update(['is_default' => true]);
                }
            }

            return $old;
        });

        Audit::log('address.deleted', 'customer_address', (int) $id, 'Address deleted: ' . implode(', ', array_filter([$old['address'], $old['city'], $old['upazila'], $old['district']])), $old, null, $norm);

        return back()->with('success', 'Address deleted. Existing orders were not changed.');
    }

    // ----- Helpers -----

    /** The normalized phone of a customer that exists, else 404 (no profile is made for a random number in the URL) */
    private function knownPhone($phone): string
    {
        $norm = Phone::normalize((string) $phone);
        abort_if($norm === null || !CustomerProfile::phoneKnown($norm), 404);

        return $norm;
    }

    /** The address, only if it belongs to this customer */
    private function owned(string $norm, $id): CustomerAddress
    {
        abort_unless(CustomerProfile::tableReady() && CustomerAddress::tableReady(), 404);
        $profile = CustomerProfile::where('phone', $norm)->first();
        abort_if(!$profile, 404);

        return CustomerAddress::where('customer_profile_id', $profile->id)->findOrFail((int) $id);
    }

    private function formData(string $norm, CustomerAddress $address): array
    {
        $locations = [];
        foreach (BdLocations::all() as $d) {
            $locations[] = ['n' => $d['n'], 'b' => $d['b'], 'u' => $d['u']];
        }

        return ['phone' => $norm, 'address' => $address, 'locations' => $locations];
    }

    private function validated(Request $request): array
    {
        $request->merge(['phone' => Phone::normalize((string) $request->input('phone')) ?? (string) $request->input('phone')]);

        $data = $request->validate([
            'recipient_name' => 'required|string|max:100',
            'phone'          => ['required', 'regex:/^01[3-9]\d{8}$/'],
            'district'       => ['required', Rule::in(BdLocations::districtNames())],
            'upazila'        => 'required|string|max:100',
            'city'           => 'required|string|max:100',
            'address'        => 'required|string|max:500',
            'landmark'       => 'nullable|string|max:255',
        ], [
            'recipient_name.required' => 'Please enter the recipient name.',
            'phone.required'          => 'Please enter the recipient mobile number.',
            'phone.regex'             => 'Please enter a valid mobile number (e.g. 01712345678).',
            'district.required'       => 'Please select the district.',
            'district.in'             => 'Please select a valid district.',
            'upazila.required'        => 'Please select the upazila.',
            'city.required'           => 'Please enter the city or town.',
            'address.required'        => 'Please enter the full address.',
        ]);

        if (!BdLocations::hasUpazila($data['district'], $data['upazila'])) {
            throw ValidationException::withMessages(['upazila' => 'Please select a valid upazila for the chosen district.']);
        }

        $data['is_default'] = $request->boolean('is_default');

        return $data;
    }

    private function fields(array $data): array
    {
        return [
            'recipient_name' => trim($data['recipient_name']),
            'phone'          => $data['phone'],
            'district'       => $data['district'],
            'upazila'        => $data['upazila'],
            'city'           => trim($data['city']),
            'address'        => trim($data['address']),
            'landmark'       => isset($data['landmark']) && trim($data['landmark']) !== '' ? trim($data['landmark']) : null,
        ];
    }

    private function refuseDuplicate($others, array $data, ?int $ignoreId): void
    {
        $key = CustomerAddress::duplicateKey($data);

        foreach ($others as $o) {
            if ($ignoreId !== null && (int) $o->id === (int) $ignoreId) {
                continue;
            }
            if (CustomerAddress::duplicateKey($o->only(['phone', 'district', 'upazila', 'city', 'address'])) === $key) {
                throw new \DomainException('This address is already saved for this customer.');
            }
        }
    }
}
