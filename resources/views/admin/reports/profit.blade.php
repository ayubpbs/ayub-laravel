@extends('admin.layout')

@section('title', 'Profit / Loss')

@section('content')
  <div class="ad-bar">
    <h1 class="ad-title">Profit / Loss</h1>
  </div>

  @include('admin.reports._nav')

  <form class="ad-filter" method="GET" action="{{ route('admin.reports.profit') }}">
    <input type="date" name="from" value="{{ $from }}" title="From" style="flex:none;min-width:150px;width:160px">
    <input type="date" name="to" value="{{ $to }}" title="To" style="flex:none;min-width:150px;width:160px">
    <select name="scope">
      <option value="all" {{ $scope === 'all' ? 'selected' : '' }}>All sales (incl. pending)</option>
      <option value="delivered" {{ $scope === 'delivered' ? 'selected' : '' }}>Delivered orders only</option>
    </select>
    <button type="submit" class="ad-btn ad-btn-gray">Show</button>
    <a class="ad-btn ad-btn-gray" href="{{ route('admin.reports.profit') }}">This month</a>
  </form>

  <div class="ad-stats">
    <div class="ad-stat"><span class="n">{{ number_format($orders) }}</span><span class="l">Orders</span></div>
    <div class="ad-stat"><span class="n">৳ {{ number_format($net) }}</span><span class="l">Net sales (after discount)</span></div>
    <div class="ad-stat"><span class="n">৳ {{ number_format($cost) }}</span><span class="l">Cost of goods sold</span></div>
    <div class="ad-stat {{ $profit < 0 ? 'ad-stat-hot' : '' }}">
      <span class="n {{ $profit < 0 ? 'ad-red' : 'ad-green' }}">{{ $profit < 0 ? '−' : '' }}৳ {{ number_format(abs($profit)) }}</span>
      <span class="l">{{ $profit < 0 ? 'Loss' : 'Gross profit' }}@if ($margin !== null) · {{ $margin }}% margin @endif</span>
    </div>
  </div>

  <p class="ad-muted">{{ \Illuminate\Support\Carbon::parse($from)->format('d M Y') }} to {{ \Illuminate\Support\Carbon::parse($to)->format('d M Y') }}.
    Net sales = product prices ৳ {{ number_format($goods) }} − coupon discount ৳ {{ number_format($discount) }}.
    Delivery charge (৳ {{ number_format($delivery) }}) is not counted because the real courier cost is not recorded per order.
    Shop expenses (rent, salary, ...) are not included; see Accounting → Profit &amp; Loss.</p>

  @if ($scope === 'all' && $pending['orders'] > 0)
    <p class="ad-muted">Included: {{ $pending['orders'] }} pending {{ $pending['orders'] === 1 ? 'order' : 'orders' }} worth ৳ {{ number_format($pending['total']) }} (not confirmed yet). Choose "Delivered orders only" to see profit that is already made.</p>
  @endif

  @if ($noCost->isNotEmpty())
    <section class="ad-card" style="border-color:#e0a800;background:#fffbea">
      <strong>⚠ Buying price is missing for {{ $noCost->count() }} {{ $noCost->count() === 1 ? 'product' : 'products' }}.</strong>
      They are counted with cost ৳ 0, so the profit above looks higher than it really is. Add the buying price in Products → Edit:
      <ul style="margin:8px 0 0 18px">
        @foreach ($noCost as $r)
          <li>
            @if ($r->product_id)<a href="{{ route('admin.products.edit', $r->product_id) }}">{{ $r->title }}</a>@else{{ $r->title }}@endif
          </li>
        @endforeach
      </ul>
    </section>
  @endif

  @if ($estimated > 0)
    <p class="ad-muted">{{ $estimated }} order {{ $estimated === 1 ? 'line is' : 'lines are' }} older than the cost-saving update, so the product's current buying price was used for {{ $estimated === 1 ? 'it' : 'them' }} (an estimate).</p>
  @endif

  @unless ($hasCostCol)
    <p class="ad-muted">The cost_price column is not in the database yet. Run php artisan migrate for exact profit on new orders.</p>
  @endunless

  <section class="ad-card">
    @if ($rows->isEmpty())
      <p class="ad-muted">No sales in this period.</p>
    @else
      <div class="ad-scroll">
        <table class="ad-table">
          <tr><th>Product</th><th class="r">Pieces</th><th class="r">Sales</th><th class="r">Cost</th><th class="r">Profit</th><th class="r">Margin</th></tr>
          @foreach ($rows as $r)
            <tr>
              <td>{{ $r->title }}@if ($r->no_cost_lines > 0) <span class="ad-st st-pending">no buying price</span>@endif</td>
              <td class="r">{{ number_format($r->qty) }}</td>
              <td class="r">৳ {{ number_format($r->amount) }}</td>
              <td class="r">৳ {{ number_format($r->cost) }}</td>
              <td class="r {{ $r->profit < 0 ? 'ad-red' : '' }}"><strong>{{ $r->profit < 0 ? '−' : '' }}৳ {{ number_format(abs($r->profit)) }}</strong></td>
              <td class="r">{{ $r->margin !== null ? $r->margin . '%' : '—' }}</td>
            </tr>
          @endforeach
          <tr>
            <td><strong>Total (before coupon discount)</strong></td>
            <td class="r"><strong>{{ number_format($rows->sum('qty')) }}</strong></td>
            <td class="r"><strong>৳ {{ number_format($goods) }}</strong></td>
            <td class="r"><strong>৳ {{ number_format($cost) }}</strong></td>
            <td class="r"><strong>৳ {{ number_format($goods - $cost) }}</strong></td>
            <td class="r">{{ $goods > 0 ? round(($goods - $cost) / $goods * 100, 1) . '%' : '—' }}</td>
          </tr>
          @if ($discount > 0)
            <tr><td colspan="4" class="r">Coupon discount</td><td class="r ad-red">− ৳ {{ number_format($discount) }}</td><td></td></tr>
            <tr>
              <td colspan="4" class="r"><strong>{{ $profit < 0 ? 'Loss' : 'Gross profit' }} after discount</strong></td>
              <td class="r {{ $profit < 0 ? 'ad-red' : 'ad-green' }}"><strong>{{ $profit < 0 ? '−' : '' }}৳ {{ number_format(abs($profit)) }}</strong></td>
              <td></td>
            </tr>
          @endif
        </table>
      </div>
    @endif
  </section>
@endsection
