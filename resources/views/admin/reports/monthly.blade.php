@extends('admin.layout')

@section('title', 'Monthly Sales')

@section('content')
  <div class="ad-bar">
    <h1 class="ad-title">Monthly Sales — {{ $start->format('F Y') }}</h1>
  </div>

  @include('admin.reports._nav')

  <form class="ad-filter" method="GET" action="{{ route('admin.reports.monthly') }}">
    <a class="ad-btn ad-btn-gray" href="{{ route('admin.reports.monthly', ['month' => $prev]) }}">← Previous month</a>
    <input type="month" name="month" value="{{ $month }}" style="flex:none;min-width:170px;width:180px">
    <button type="submit" class="ad-btn ad-btn-gray">Show</button>
    @if (!$isNow)<a class="ad-btn ad-btn-gray" href="{{ route('admin.reports.monthly') }}">This month</a>@endif
    <a class="ad-btn ad-btn-gray" href="{{ route('admin.reports.monthly', ['month' => $next]) }}">Next month →</a>
  </form>

  <div class="ad-stats">
    <div class="ad-stat"><span class="n">{{ number_format($sum['orders']) }}</span><span class="l">Orders</span></div>
    <div class="ad-stat"><span class="n">৳ {{ number_format($sum['total']) }}</span><span class="l">Total sales</span></div>
    <div class="ad-stat"><span class="n">৳ {{ number_format($sum['subtotal']) }}</span><span class="l">Goods value</span></div>
    <div class="ad-stat"><span class="n">৳ {{ number_format($sum['discount']) }}</span><span class="l">Discount given</span></div>
    <div class="ad-stat"><span class="n">৳ {{ number_format($sum['delivery']) }}</span><span class="l">Delivery charge</span></div>
    <div class="ad-stat"><span class="n">৳ {{ number_format($avgDay) }}</span><span class="l">Average per selling day</span></div>
  </div>

  @if ($sum['pending_orders'] > 0)
    <p class="ad-muted">Included above: {{ $sum['pending_orders'] }} pending {{ $sum['pending_orders'] === 1 ? 'order' : 'orders' }} worth ৳ {{ number_format($sum['pending_total']) }} (not confirmed yet). Cancelled and returned orders are not counted.</p>
  @else
    <p class="ad-muted">Cancelled and returned orders are not counted.</p>
  @endif

  <section class="ad-card">
    @if ($rows->isEmpty())
      <p class="ad-muted">No orders in this month.</p>
    @else
      <div class="ad-scroll">
        <table class="ad-table">
          <tr><th>Date</th><th class="r">Orders</th><th class="r">Goods value</th><th class="r">Discount</th><th class="r">Delivery</th><th class="r">Total</th><th></th></tr>
          @foreach ($rows as $r)
            <tr>
              <td><a href="{{ route('admin.reports.daily', ['date' => $r->d]) }}">{{ \Illuminate\Support\Carbon::parse($r->d)->format('D, d M') }}</a>
                @if ((int) $r->pending_orders > 0)<br><small class="ad-muted">{{ (int) $r->pending_orders }} pending</small>@endif</td>
              <td class="r">{{ number_format($r->orders) }}</td>
              <td class="r">৳ {{ number_format($r->subtotal) }}</td>
              <td class="r">৳ {{ number_format($r->discount) }}</td>
              <td class="r">৳ {{ number_format($r->delivery) }}</td>
              <td class="r"><strong>৳ {{ number_format($r->total) }}</strong></td>
              <td><div class="ad-bar-bg"><span class="ad-bar-chart" style="width:{{ max(2, round($r->total / $maxDay * 100)) }}%"></span></div></td>
            </tr>
          @endforeach
          <tr>
            <td><strong>Month total</strong></td>
            <td class="r"><strong>{{ number_format($sum['orders']) }}</strong></td>
            <td class="r"><strong>৳ {{ number_format($sum['subtotal']) }}</strong></td>
            <td class="r"><strong>৳ {{ number_format($sum['discount']) }}</strong></td>
            <td class="r"><strong>৳ {{ number_format($sum['delivery']) }}</strong></td>
            <td class="r"><strong>৳ {{ number_format($sum['total']) }}</strong></td>
            <td></td>
          </tr>
        </table>
      </div>
      @if ($best)
        <p class="ad-muted" style="margin:12px 0 0">Best day: {{ \Illuminate\Support\Carbon::parse($best->d)->format('d M') }} with ৳ {{ number_format($best->total) }} from {{ (int) $best->orders }} {{ (int) $best->orders === 1 ? 'order' : 'orders' }}.</p>
      @endif
    @endif
  </section>
@endsection
