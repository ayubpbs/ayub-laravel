<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CouponController extends Controller
{
    public function index()
    {
        $coupons = Coupon::orderByDesc('id')->get();

        return view('admin.coupons.index', compact('coupons'));
    }

    public function create()
    {
        return view('admin.coupons.form', ['coupon' => new Coupon(['type' => 'percent', 'is_active' => true])]);
    }

    public function store(Request $request)
    {
        Coupon::create($this->validated($request));

        return redirect()->route('admin.coupons.index')->with('success', 'Coupon created.');
    }

    public function edit($id)
    {
        return view('admin.coupons.form', ['coupon' => Coupon::findOrFail($id)]);
    }

    public function update(Request $request, $id)
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->update($this->validated($request, $coupon->id));

        return redirect()->route('admin.coupons.index')->with('success', 'Coupon updated.');
    }

    public function destroy($id)
    {
        // Past orders keep the coupon code they used, so deleting a coupon is safe
        Coupon::findOrFail($id)->delete();

        return redirect()->route('admin.coupons.index')->with('success', 'Coupon deleted.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $request->merge(['code' => Coupon::normalize($request->input('code'))]);

        $data = $request->validate([
            'code'         => ['required', 'string', 'min:3', 'max:30', 'regex:/^[A-Z0-9_\-]+$/', Rule::unique('coupons', 'code')->ignore($ignoreId)],
            'type'         => ['required', Rule::in(array_keys(Coupon::types()))],
            'value'        => ['required', 'integer', 'min:1', 'max:10000000'],
            'min_order'    => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'max_discount' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'usage_limit'  => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'starts_at'    => ['nullable', 'date'],
            'expires_at'   => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active'    => ['nullable', 'boolean'],
            'note'         => ['nullable', 'string', 'max:150'],
        ], [
            'code.regex'                  => 'The code can only contain letters, numbers, - and _ (no spaces).',
            'code.unique'                 => 'A coupon with this code already exists.',
            'expires_at.after_or_equal'   => 'The expiry date cannot be before the start date.',
        ]);

        if ($data['type'] === 'percent' && (int) $data['value'] > 100) {
            throw ValidationException::withMessages(['value' => 'A percentage coupon cannot be more than 100%.']);
        }

        return [
            'code'         => $data['code'],
            'type'         => $data['type'],
            'value'        => (int) $data['value'],
            'min_order'    => (int) ($data['min_order'] ?? 0),
            // the cap only makes sense for percentage coupons
            'max_discount' => $data['type'] === 'percent' && !empty($data['max_discount']) ? (int) $data['max_discount'] : null,
            'usage_limit'  => !empty($data['usage_limit']) ? (int) $data['usage_limit'] : null,
            'starts_at'    => !empty($data['starts_at']) ? Carbon::parse($data['starts_at'])->startOfDay() : null,
            'expires_at'   => !empty($data['expires_at']) ? Carbon::parse($data['expires_at'])->endOfDay() : null,
            'is_active'    => $request->boolean('is_active'),
            'note'         => isset($data['note']) ? trim($data['note']) : null,
        ];
    }
}
