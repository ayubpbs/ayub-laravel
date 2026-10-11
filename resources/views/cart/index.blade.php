@extends('layouts.app')

@section('title', 'Cart — ' . config('shop.name'))

@section('content')
  <section class="section">
    <h1 class="page-title">🛒 Your Cart</h1>

    @if (empty($lines))
      <div class="empty">
        <p>Your cart is empty.</p>
        <a class="btn-primary" href="{{ route('home') }}">Start shopping</a>
      </div>
    @else
      <div class="cart-wrap">
        <div class="cart-lines">
          @foreach ($lines as $line)
            @php $p = $line['product']; @endphp
            <div class="cart-line">
              <a class="cart-thumb" href="{{ $p->url() }}">
                @if ($p->image)
                  <img src="{{ asset('uploads/products/' . $p->image) }}" alt="{{ $p->title }}">
                @else
                  <span class="emoji">{{ $p->icon ?: '📦' }}</span>
                @endif
              </a>

              <div class="cart-info">
                <a class="product-title" href="{{ $p->url() }}">{{ $p->title }}</a>
                <span class="product-brand">{{ $p->brand }}</span>
                <span class="price-now">৳ {{ number_format($p->price) }}</span>
              </div>

              <form class="cart-qty" action="{{ route('cart.update', $p->id) }}" method="POST">
                @csrf
                <input type="number" name="quantity" value="{{ $line['qty'] }}" min="1" max="{{ $p->stock_quantity }}">
                <button type="submit" class="btn-small">Update</button>
              </form>

              <div class="cart-total">৳ {{ number_format($line['total']) }}</div>

              <form action="{{ route('cart.remove', $p->id) }}" method="POST">
                @csrf
                <button type="submit" class="btn-remove" title="Remove">✕</button>
              </form>
            </div>
          @endforeach
        </div>

        <aside class="summary">
          <h2>Order Summary</h2>
          <div class="sum-row"><span>Subtotal</span><span>৳ {{ number_format($subtotal) }}</span></div>
          <div class="sum-row"><span>Delivery charge</span><span>Calculated at checkout</span></div>
          <div class="sum-row sum-total"><span>Total</span><span>৳ {{ number_format($total) }}</span></div>
          <a class="btn-primary btn-block" href="{{ route('checkout.show') }}">Proceed to Checkout</a>
          <a class="continue" href="{{ route('home') }}">← Continue shopping</a>
        </aside>
      </div>
    @endif
  </section>
@endsection
