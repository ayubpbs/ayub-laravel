@extends('admin.layout')

@section('title', 'Order #' . $order->id)

@section('content')
  <p class="ad-crumbs"><a href="{{ route('admin.orders.index') }}">← All orders</a></p>
  <h1 class="ad-title">Order #{{ $order->id }} <span class="ad-st st-{{ $order->status }}">{{ $order->statusLabel() }}</span></h1>

  <div class="ad-two">
    <section class="ad-card">
      <h2>Customer details</h2>
      <dl class="ad-dl">
        <dt>Name</dt><dd>{{ $order->name }}</dd>
        <dt>Mobile</dt><dd><a href="tel:{{ $order->phone }}">{{ $order->phone }}</a></dd>
        <dt>Address</dt><dd>{{ $order->address }}</dd>
        @if ($order->note)
          <dt>Note</dt><dd>{{ $order->note }}</dd>
        @endif
        <dt>Order time</dt><dd>{{ $order->created_at->format('d M Y, h:i A') }}</dd>
        <dt>Payment</dt><dd>Cash on delivery</dd>
      </dl>
    </section>

    <section class="ad-card">
      <h2>Change status</h2>
      @if ($order->status === 'cancelled')
        <p class="ad-muted">This order has been cancelled, so its status can no longer be changed.</p>
      @else
        <form action="{{ route('admin.orders.status', $order->id) }}" method="POST" class="ad-form">
          @csrf
          <select name="status">
            @foreach (\App\Models\Order::statuses() as $key => $label)
              <option value="{{ $key }}" {{ $order->status === $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
          <button type="submit" class="ad-btn">Save</button>
        </form>
        <p class="ad-hint">Choosing "Cancelled" puts the products back in stock, and the status cannot be changed afterwards.</p>
      @endif
    </section>
  </div>

  <section class="ad-card">
    <h2>Items</h2>
    <div class="ad-scroll">
      <table class="ad-table">
        <tr><th>Product</th><th>Price</th><th>Quantity</th><th class="r">Total</th></tr>
        @foreach ($order->items as $item)
          <tr>
            <td>{{ $item->title }}</td>
            <td>৳ {{ number_format($item->price) }}</td>
            <td>{{ $item->quantity }}</td>
            <td class="r">৳ {{ number_format($item->line_total) }}</td>
          </tr>
        @endforeach
        <tr><td colspan="3" class="r">Subtotal</td><td class="r">৳ {{ number_format($order->subtotal) }}</td></tr>
        <tr><td colspan="3" class="r">Delivery charge</td><td class="r">৳ {{ number_format($order->delivery_charge) }}</td></tr>
        <tr class="ad-total"><td colspan="3" class="r">Total</td><td class="r">৳ {{ number_format($order->total) }}</td></tr>
      </table>
    </div>
  </section>
@endsection
