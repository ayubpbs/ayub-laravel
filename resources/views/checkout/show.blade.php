@extends('layouts.app')

@section('title', 'Checkout — ' . config('shop.name'))

@section('content')
  <section class="section">
    <p class="crumbs"><a href="{{ route('cart.index') }}">← Back to cart</a></p>
    <h1 class="page-title">Complete your order</h1>

    @if ($errors->any())
      <div class="flash flash-err">
        @foreach ($errors->all() as $e)
          <div>{{ $e }}</div>
        @endforeach
      </div>
    @endif

    <div class="cart-wrap">
      <form class="checkout-form" action="{{ route('checkout.store') }}" method="POST">
        @csrf

        <label>Your name *
          <input type="text" name="name" value="{{ old('name') }}" required maxlength="100" autocomplete="name">
        </label>

        <label>Mobile number *
          <input type="tel" name="phone" value="{{ old('phone') }}" required placeholder="01XXXXXXXXX" autocomplete="tel">
        </label>

        <label>Delivery address *
          <textarea name="address" rows="3" required maxlength="500" placeholder="House/road, area, thana, district">{{ old('address') }}</textarea>
        </label>

        <label>Anything else we should know? (optional)
          <textarea name="note" rows="2" maxlength="500">{{ old('note') }}</textarea>
        </label>

        <p class="pay-note">💵 Payment: Cash on delivery (pay when you receive the product)</p>

        <button type="submit" class="btn-primary btn-block">✔ Place Order</button>
      </form>

      <aside class="summary">
        <h2>Your Order</h2>
        @foreach ($lines as $line)
          <div class="sum-row">
            <span>{{ $line['product']->title }} × {{ $line['qty'] }}</span>
            <span>৳ {{ number_format($line['total']) }}</span>
          </div>
        @endforeach
        <hr>
        <div class="sum-row"><span>Subtotal</span><span>৳ {{ number_format($subtotal) }}</span></div>
        <div class="sum-row"><span>Delivery charge</span><span>৳ {{ number_format($delivery) }}</span></div>
        <div class="sum-row sum-total"><span>Total</span><span>৳ {{ number_format($total) }}</span></div>
      </aside>
    </div>
  </section>
@endsection
