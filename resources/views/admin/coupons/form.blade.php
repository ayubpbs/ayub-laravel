@extends('admin.layout')

@php
  $editing = $coupon->exists;
  $type    = old('type', $coupon->type ?: 'percent');
@endphp

@section('title', $editing ? 'Edit Coupon' : 'New Coupon')

@section('content')
  <p class="ad-crumbs"><a href="{{ route('admin.coupons.index') }}">← All coupons</a></p>
  <h1 class="ad-title">{{ $editing ? 'Edit Coupon ' . $coupon->code : 'New Coupon' }}</h1>

  <form class="ad-card ad-form" method="POST"
        action="{{ $editing ? route('admin.coupons.update', $coupon->id) : route('admin.coupons.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="ad-row">
      <label>Coupon code *
        <input type="text" name="code" id="cp-code" value="{{ old('code', $coupon->code) }}" required minlength="3" maxlength="30" placeholder="e.g. WELCOME10" style="text-transform:uppercase">
        <small>Letters, numbers, - and _ only. Customers can type it in any case. <a href="#" id="cp-gen" class="ad-link">Generate a random code</a></small>
      </label>
      <label>Internal note <small>(only you see this)</small>
        <input type="text" name="note" value="{{ old('note', $coupon->note) }}" maxlength="150" placeholder="e.g. Eid campaign">
      </label>
    </div>

    <div class="ad-row">
      <label>Discount type *
        <select name="type" id="cp-type">
          @foreach (\App\Models\Coupon::types() as $key => $label)
            <option value="{{ $key }}" {{ $type === $key ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
      </label>
      <label><span id="cp-value-label">{{ $type === 'percent' ? 'Discount (%)' : 'Discount (৳)' }}</span> *
        <input type="number" name="value" value="{{ old('value', $coupon->value) }}" required min="1" max="10000000" step="1">
      </label>
      <label id="cp-max-wrap">Maximum discount (৳) <small>(optional, caps a percentage coupon)</small>
        <input type="number" name="max_discount" value="{{ old('max_discount', $coupon->max_discount) }}" min="1" step="1" placeholder="No cap">
      </label>
    </div>

    <div class="ad-row">
      <label>Minimum order (৳) <small>(subtotal needed to use it; 0 = none)</small>
        <input type="number" name="min_order" value="{{ old('min_order', $coupon->min_order ?? 0) }}" min="0" step="1">
      </label>
      <label>Usage limit <small>(total times it can be used; empty = unlimited)</small>
        <input type="number" name="usage_limit" value="{{ old('usage_limit', $coupon->usage_limit) }}" min="1" step="1" placeholder="Unlimited">
      </label>
    </div>

    <div class="ad-row">
      <label>Start date <small>(optional)</small>
        <input type="date" name="starts_at" value="{{ old('starts_at', optional($coupon->starts_at)->format('Y-m-d')) }}">
      </label>
      <label>Expiry date <small>(optional — valid until the end of this day)</small>
        <input type="date" name="expires_at" value="{{ old('expires_at', optional($coupon->expires_at)->format('Y-m-d')) }}">
      </label>
    </div>

    <input type="hidden" name="is_active" value="0">
    <label class="ad-check"><input type="checkbox" name="is_active" value="1" {{ old('is_active', $coupon->is_active ? 1 : 0) ? 'checked' : '' }}> Coupon is active</label>

    @if ($editing)
      <p class="ad-hint">Used {{ $coupon->used_count }} time(s). A customer can apply only one coupon per order.</p>
    @else
      <p class="ad-hint">A customer can apply only one coupon per order. The discount is taken off the products total, not the delivery charge.</p>
    @endif

    <div class="ad-submit">
      <button type="submit" class="ad-btn">{{ $editing ? 'Update' : 'Create Coupon' }}</button>
      <a class="ad-btn ad-btn-gray" href="{{ route('admin.coupons.index') }}">Cancel</a>
    </div>
  </form>

  <script>
    (function () {
      var type = document.getElementById('cp-type');
      var label = document.getElementById('cp-value-label');
      var max = document.getElementById('cp-max-wrap');
      function sync() {
        var pct = type.value === 'percent';
        label.textContent = pct ? 'Discount (%)' : 'Discount (৳)';
        max.style.display = pct ? '' : 'none';
      }
      type.addEventListener('change', sync);
      sync();

      document.getElementById('cp-gen').addEventListener('click', function (e) {
        e.preventDefault();
        var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789', out = '';
        for (var i = 0; i < 8; i++) out += chars.charAt(Math.floor(Math.random() * chars.length));
        document.getElementById('cp-code').value = out;
      });
    })();
  </script>
@endsection
