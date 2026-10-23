@extends('admin.layout')

@section('title', 'Daily Sales')

@section('content')
  <div class="ad-bar">
    <h1 class="ad-title">Daily Sales — {{ $day->format('d M Y') }}</h1>
  </div>

  @include('admin.reports._nav')

  <form class="ad-filter" method="GET" action="{{ route('admin.reports.daily') }}">
    <a class="ad-btn ad-btn-gray" href="{{ route('admin.reports.daily', ['date' => $prev]) }}">← Previous day</a>
    <input type="date" name="date" value="{{ $date }}" style="flex:none;min-width:150px;width:160px">
    <button type="submit" class="ad-btn ad-btn-gray">Show</button>
    @if (!$isToday)<a class="ad-btn ad-btn-gray" href="{{ route('admin.reports.daily') }}">Today</a>@endif
    <a class="ad-btn ad-btn-gray" href="{{ route('admin.reports.daily', ['date' => $next]) }}">Next day →</a>
  </form>

  <div class="ad-stats">
    <div class="ad-stat"><span class="n">{{ number_format($sum['orders']) }}</span><span class="l">Orders</span></div>
    <div class="ad-stat"><span class="n">৳ {{ number_format($sum['total']) }}</span><span class="l">Total sales</span></div>
    <div class="ad-stat"><span class="n">৳ {{ number_format($sum['subtotal']) }}</span><span class="l">Goods value</span></div>
    <div class="ad-stat"><span class="n">৳ {{ number_format($sum['discount']) }}</span><span class="l">Discount given</span></div>
    <div class="ad-stat"><span class="n">৳ {{ number_format($sum['delivery']) }}</span><span class="l">Delivery charge</span></div>
  </div>

  @if ($sum['pending_orders'] > 0)
    <p class="ad-muted">Included above: {{ $sum['pending_orders'] }} pending {{ $sum['pending_orders'] === 1 ? 'order' : 'orders' }} worth ৳ {{ number_format($sum['pending_total']) }} (not confirmed yet). Cancelled and returned orders are not counted.</p>
  @else
    <p class="ad-muted">Cancelled and returned orders are not counted.</p>
  @endif

  <section class="ad-card">
    @if ($orders->isEmpty())
      <p class="ad-muted">No orders on this day.</p>
    @else
      <div class="ad-scroll">
        <table class="ad-table">
          <tr><th>Order</th><th>Time</th><th>Customer</th><th>Payment</th><th>Status</th><th class="r">Total</th></tr>
          @foreach ($orders as $o)
            <tr>
              <td><a href="{{ route('admin.orders.show', $o->id) }}"><strong>#{{ $o->id }}</strong></a></td>
              <td>{{ $o->created_at->format('h:i A') }}</td>
              <td>{{ $o->name }}<br><small class="ad-muted">{{ $o->phone }}</small></td>
              <td>{{ $o->paymentMethodLabel() }}</td>
              <td><span class="ad-st st-{{ $o->status }}">{{ $o->statusLabel() }}</span></td>
              <td class="r"><strong>৳ {{ number_format($o->total) }}</strong></td>
            </tr>
          @endforeach
          <tr>
            <td colspan="5" class="r"><strong>Total</strong></td>
            <td class="r"><strong>৳ {{ number_format($sum['total']) }}</strong></td>
          </tr>
        </table>
      </div>
    @endif
  </section>
@endsection
