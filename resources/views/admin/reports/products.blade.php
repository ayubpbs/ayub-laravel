@extends('admin.layout')

@section('title', 'Product-wise Sales')

@section('content')
  <div class="ad-bar">
    <h1 class="ad-title">Product-wise Sales</h1>
  </div>

  @include('admin.reports._nav')

  <form class="ad-filter" method="GET" action="{{ route('admin.reports.products') }}">
    <input type="date" name="from" value="{{ $from }}" title="From" style="flex:none;min-width:150px;width:160px">
    <input type="date" name="to" value="{{ $to }}" title="To" style="flex:none;min-width:150px;width:160px">
    <select name="sort">
      <option value="qty" {{ $sort === 'qty' ? 'selected' : '' }}>Most pieces sold first</option>
      <option value="amount" {{ $sort === 'amount' ? 'selected' : '' }}>Highest amount first</option>
    </select>
    <button type="submit" class="ad-btn ad-btn-gray">Show</button>
    <a class="ad-btn ad-btn-gray" href="{{ route('admin.reports.products') }}">This month</a>
  </form>

  <div class="ad-stats">
    <div class="ad-stat"><span class="n">{{ number_format($rows->count()) }}</span><span class="l">Different products sold</span></div>
    <div class="ad-stat"><span class="n">{{ number_format($qty) }}</span><span class="l">Pieces sold</span></div>
    <div class="ad-stat"><span class="n">৳ {{ number_format($amount) }}</span><span class="l">Product sales value</span></div>
  </div>

  <p class="ad-muted">{{ \Illuminate\Support\Carbon::parse($from)->format('d M Y') }} to {{ \Illuminate\Support\Carbon::parse($to)->format('d M Y') }}. Amounts are product prices only (before coupon discount and delivery charge). Pending orders are included; cancelled, returned, replacement and exchange orders are not.</p>

  <section class="ad-card">
    @if ($rows->isEmpty())
      <p class="ad-muted">No products sold in this period.</p>
    @else
      <div class="ad-scroll">
        <table class="ad-table">
          <tr><th>#</th><th>Product</th><th class="r">Pieces</th><th class="r">Orders</th><th class="r">Amount</th><th></th></tr>
          @foreach ($rows as $i => $r)
            <tr>
              <td>{{ $i + 1 }}</td>
              <td>{{ $r->title }}</td>
              <td class="r"><strong>{{ number_format($r->qty) }}</strong></td>
              <td class="r">{{ number_format($r->orders) }}</td>
              <td class="r"><strong>৳ {{ number_format($r->amount) }}</strong></td>
              <td><div class="ad-bar-bg"><span class="ad-bar-chart" style="width:{{ max(2, round($r->amount / $maxAmt * 100)) }}%"></span></div></td>
            </tr>
          @endforeach
          <tr>
            <td></td>
            <td><strong>Total</strong></td>
            <td class="r"><strong>{{ number_format($qty) }}</strong></td>
            <td></td>
            <td class="r"><strong>৳ {{ number_format($amount) }}</strong></td>
            <td></td>
          </tr>
        </table>
      </div>
    @endif
  </section>
@endsection
