@extends('admin.layout')

@section('title', 'Add Customer')

@section('content')
  <p class="ad-crumbs"><a href="{{ route('admin.customers.index') }}">← All customers</a></p>
  <h1 class="ad-title">Add Customer</h1>

  <form class="ad-card ad-form" method="POST" action="{{ route('admin.customers.store') }}">
    @csrf

    <label>Mobile number *
      <input type="text" name="phone" value="{{ old('phone', $phone) }}" required maxlength="30" placeholder="01712345678" inputmode="tel">
      <small>Any format works (+880…, 880…, Bangla digits). It is saved as 01XXXXXXXXX. If this number is already a customer, you are taken to that customer.</small>
    </label>

    <label>Name *
      <input type="text" name="name" value="{{ old('name') }}" required maxlength="100">
    </label>

    <label>Internal note <small>(optional, only staff can see it)</small>
      <textarea name="notes" rows="3" maxlength="500">{{ old('notes') }}</textarea>
    </label>

    <div class="ad-submit">
      <button type="submit" class="ad-btn">Add Customer</button>
      <a class="ad-btn ad-btn-gray" href="{{ route('admin.customers.index') }}">Cancel</a>
    </div>
  </form>
@endsection
