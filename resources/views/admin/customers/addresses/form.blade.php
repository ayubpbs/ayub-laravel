@extends('admin.layout')

@php $editing = $address->exists; @endphp

@section('title', $editing ? 'Edit Address' : 'Add Address')

@section('content')
  <p class="ad-crumbs"><a href="{{ route('admin.customers.show', $phone) }}">← Back to customer {{ $phone }}</a></p>
  <h1 class="ad-title">{{ $editing ? 'Edit Address' : 'Add Address' }}</h1>

  <form class="ad-card ad-form" method="POST"
        action="{{ $editing ? route('admin.customers.addresses.update', [$phone, $address->id]) : route('admin.customers.addresses.store', $phone) }}">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="ad-row">
      <label>Recipient name *
        <input type="text" name="recipient_name" value="{{ old('recipient_name', $address->recipient_name) }}" required maxlength="100">
      </label>
      <label>Recipient mobile *
        <input type="text" name="phone" value="{{ old('phone', $address->phone) }}" required maxlength="30" inputmode="tel">
      </label>
    </div>

    <div class="ad-row">
      <label>District *
        <select name="district" id="f-district" required><option value="">Select district</option></select>
      </label>
      <label>Upazila / Thana *
        <select name="upazila" id="f-upazila" required disabled><option value="">Select district first</option></select>
      </label>
      <label>City / Town *
        <input type="text" name="city" value="{{ old('city', $address->city) }}" required maxlength="100">
      </label>
    </div>

    <label>Full address *
      <textarea name="address" rows="2" required maxlength="500">{{ old('address', $address->address) }}</textarea>
    </label>

    <label>Landmark / delivery instructions <small>(optional)</small>
      <input type="text" name="landmark" value="{{ old('landmark', $address->landmark) }}" maxlength="255">
    </label>

    <label class="ad-check">
      <input type="checkbox" name="is_default" value="1" {{ old('is_default', $address->is_default) ? 'checked' : '' }} {{ $editing && $address->is_default ? 'disabled' : '' }}>
      Use as the default address
      @if ($editing && $address->is_default)<small>(this is the default now; to change it, make another address the default)</small>@endif
    </label>

    <div class="ad-submit">
      <button type="submit" class="ad-btn">{{ $editing ? 'Update address' : 'Save address' }}</button>
      <a class="ad-btn ad-btn-gray" href="{{ route('admin.customers.show', $phone) }}">Cancel</a>
    </div>
  </form>

  <script>
    (function () {
      var data = @json($locations);
      var selDistrict = @json(old('district', $address->district));
      var selUpazila  = @json(old('upazila', $address->upazila));
      var dEl = document.getElementById('f-district'), uEl = document.getElementById('f-upazila');

      function opt(value, label, selected) {
        var o = document.createElement('option');
        o.value = value; o.textContent = label; if (selected) o.selected = true;
        return o;
      }
      data.forEach(function (d) { dEl.appendChild(opt(d.n, d.n + ' (' + d.b + ')', d.n === selDistrict)); });

      function fillUpazilas(name, keep) {
        uEl.innerHTML = '';
        var d = data.filter(function (x) { return x.n === name; })[0];
        if (!d) { uEl.appendChild(opt('', 'Select district first')); uEl.disabled = true; return; }
        uEl.disabled = false;
        uEl.appendChild(opt('', 'Select upazila'));
        d.u.forEach(function (u) { uEl.appendChild(opt(u[0], u[0] + ' (' + u[1] + ')', keep && u[0] === selUpazila)); });
      }
      dEl.addEventListener('change', function () { fillUpazilas(dEl.value, false); });
      fillUpazilas(selDistrict, true);
    })();
  </script>
@endsection
