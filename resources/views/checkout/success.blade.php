@extends('layouts.app')

@section('title', 'Order Complete — ' . config('shop.name'))

@push('head')
  <link rel="stylesheet" href="{{ asset('assets/css/checkout.css') }}">
@endpush

@section('content')
  <section class="section">
    <div class="success-box">
      <div class="success-icon">✔</div>
      <h1>Thank you, {{ $order->name }}!</h1>
      <p>Your order has been received. Order number: <strong>#{{ $order->id }}</strong></p>
      <p>We will call <strong>{{ $order->phone }}</strong> shortly to confirm your order.</p>

      <div class="summary success-summary">
        @foreach ($order->items as $item)
          <div class="sum-row">
            <span>{{ $item->title }} × {{ $item->quantity }}</span>
            <span>৳ {{ number_format($item->line_total) }}</span>
          </div>
        @endforeach
        <hr>
        @if ($order->discount > 0)
          <div class="sum-row"><span>Discount{{ $order->coupon_code ? ' (' . $order->coupon_code . ')' : '' }}</span><span>− ৳ {{ number_format($order->discount) }}</span></div>
        @endif
        <div class="sum-row"><span>Delivery charge</span><span>৳ {{ number_format($order->delivery_charge) }}</span></div>
        <div class="sum-row sum-total"><span>Total{{ $order->isBkash() ? '' : ' (cash on delivery)' }}</span><span>৳ {{ number_format($order->total) }}</span></div>
        <p class="addr">📍 {{ $order->address }}</p>
      </div>

      @if ($order->isBkash())
        <div class="bk-done">
          <strong>bKash payment received for checking</strong>
          <span>You told us you sent ৳ {{ number_format((int) $order->bkash_amount) }}@if ($order->bkash_trx_id) (TrxID {{ $order->bkash_trx_id }})@endif. We will verify it shortly and update your invoice.</span>
        </div>
      @endif

      <a class="btn-primary" href="{{ route('home') }}">Continue shopping</a>
    </div>
  </section>
@endsection
