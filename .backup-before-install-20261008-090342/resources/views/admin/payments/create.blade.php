@extends('admin.layout')

@section('title', 'Record Payment')

@section('content')
  <p class="ad-crumbs"><a href="{{ route('admin.payments.index') }}">← All payments</a></p>
  <h1 class="ad-title">Record Payment</h1>

  @if (!$order)
    <form class="ad-card ad-form" method="GET" action="{{ route('admin.payments.create') }}">
      <label>Order number
        <input type="text" name="order" placeholder="e.g. 125" required autofocus>
      </label>
      <div class="ad-submit"><button type="submit" class="ad-btn">Find order</button></div>
    </form>
  @else
    @php
      $net    = $order->netPaid();
      $due    = $order->dueAmount();
      $locked = $order->isLocked();
    @endphp

    <section class="ad-card">
      <div class="ad-card-head">
        <h2>Order #{{ $order->id }} — {{ $order->name }}</h2>
        <a href="{{ route('admin.orders.show', $order->id) }}">View order →</a>
      </div>
      <dl class="ad-dl">
        <dt>Mobile</dt><dd>{{ $order->phone }}</dd>
        <dt>Order status</dt><dd><span class="ad-st st-{{ $order->status }}">{{ $order->statusLabel() }}</span></dd>
        <dt>Order total</dt><dd>৳ {{ number_format($order->total) }}</dd>
        <dt>Received so far</dt><dd>৳ {{ number_format($net) }}</dd>
        <dt>Due</dt><dd><strong class="{{ $due > 0 ? 'ad-red' : '' }}">৳ {{ number_format($due) }}</strong> <span class="ad-st {{ \App\Models\Order::paymentBadge($order->paymentStatus()) }}">{{ $order->paymentLabel() }}</span></dd>
      </dl>
    </section>

    @if ($locked && $net <= 0)
      <div class="ad-flash ad-warn">This order is {{ strtolower($order->statusLabel()) }} and nothing was received, so there is nothing to record.</div>
    @elseif ($due <= 0 && $net <= 0)
      <div class="ad-flash ad-warn">Nothing to record for this order.</div>
    @else
      @include('admin.payments._form', ['order' => $order, 'due' => $due, 'net' => $net, 'locked' => $locked, 'return' => 'payments'])
    @endif

    <p><a class="ad-link" href="{{ route('admin.payments.create') }}">Choose another order</a></p>
  @endif
@endsection
