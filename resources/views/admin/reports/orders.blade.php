@extends('admin.layout')

@section('title', $label . ' Orders')

@section('content')
  <div class="ad-bar">
    <h1 class="ad-title">{{ $label }} Orders</h1>
  </div>

  @include('admin.reports._nav')

  <form class="ad-filter" method="GET" action="{{ route('admin.reports.orders', $status) }}">
    <input type="text" name="q" value="{{ $q }}" placeholder="Order no., name or mobile…">
    <input type="date" name="from" value="{{ $from }}" title="From" style="flex:none;min-width:150px;width:160px">
    <input type="date" name="to" value="{{ $to }}" title="To" style="flex:none;min-width:150px;width:160px">
    <button type="submit" class="ad-btn ad-btn-gray">Show</button>
    <a class="ad-btn ad-btn-gray" href="{{ route('admin.reports.orders', $status) }}">This month</a>
  </form>

  <div class="ad-stats">
    <div class="ad-stat"><span class="n">{{ number_format($count) }}</span><span class="l">{{ $label }} orders</span></div>
    <div class="ad-stat"><span class="n">৳ {{ number_format($total) }}</span><span class="l">{{ $status === 'delivered' ? 'Total value' : 'Order value ' . ($status === 'cancelled' ? 'cancelled' : 'returned') }}</span></div>
    @if ($status === 'delivered')
      <div class="ad-stat"><span class="n">৳ {{ number_format($subtotal) }}</span><span class="l">Goods value</span></div>
      <div class="ad-stat"><span class="n">৳ {{ number_format($discount) }}</span><span class="l">Discount given</span></div>
      <div class="ad-stat"><span class="n">৳ {{ number_format($delivery) }}</span><span class="l">Delivery charge</span></div>
      <div class="ad-stat {{ $owed > 0 ? 'ad-stat-hot' : '' }}"><span class="n">৳ {{ number_format($owed) }}</span><span class="l">Still to collect ({{ $owedOrders }} {{ $owedOrders === 1 ? 'order' : 'orders' }})</span></div>
    @else
      <div class="ad-stat {{ $held > 0 ? 'ad-stat-hot' : '' }}"><span class="n">৳ {{ number_format($held) }}</span><span class="l">Money still held, to refund ({{ $heldOrders }} {{ $heldOrders === 1 ? 'order' : 'orders' }})</span></div>
    @endif
  </div>

  @if ($status === 'delivered')
    <p class="ad-muted">Dated by delivery date. Replacement and exchange orders are not listed.</p>
  @else
    <p class="ad-muted">Cancelled and returned orders have no date of their own, so "Status changed" is the last time the order was updated. It can be later than the real date if the order was edited afterwards (for example a refund was recorded). Stock of these orders was already put back. Replacement and exchange orders are not listed.</p>
  @endif

  @if ($products->isNotEmpty())
    <section class="ad-card">
      <h2>Products in {{ strtolower($label) }} orders</h2>
      <div class="ad-scroll">
        <table class="ad-table">
          <tr><th>Product</th><th class="r">Pieces</th><th class="r">Amount</th></tr>
          @foreach ($products as $p)
            <tr>
              <td>{{ $p->title }}</td>
              <td class="r"><strong>{{ number_format($p->qty) }}</strong></td>
              <td class="r">৳ {{ number_format($p->amount) }}</td>
            </tr>
          @endforeach
        </table>
      </div>
    </section>
  @endif

  <section class="ad-card">
    @if ($orders->isEmpty())
      <p class="ad-muted">No {{ strtolower($label) }} orders found.</p>
    @else
      <div class="ad-scroll">
        <table class="ad-table">
          <tr>
            <th>Order</th>
            <th>Ordered</th>
            <th>{{ $status === 'delivered' ? 'Delivered' : 'Status changed' }}</th>
            <th>Customer</th>
            <th>Payment</th>
            @if ($status !== 'cancelled')<th>Courier</th>@endif
            <th class="r">Total</th>
          </tr>
          @foreach ($orders as $o)
            @php
              $changed = $status === 'delivered' ? ($o->delivered_at ?: $o->updated_at) : $o->updated_at;
            @endphp
            <tr>
              <td><a href="{{ route('admin.orders.show', $o->id) }}"><strong>#{{ $o->id }}</strong></a></td>
              <td>{{ $o->created_at->format('d M Y') }}</td>
              <td>{{ $changed->format('d M Y, h:i A') }}</td>
              <td>{{ $o->name }}<br><small class="ad-muted">{{ $o->phone }}</small></td>
              <td>{{ $o->paymentMethodLabel() }}<br><span class="ad-st {{ \App\Models\Order::paymentBadge($o->paymentStatus()) }}">{{ $o->paymentLabel() }}</span></td>
              @if ($status !== 'cancelled')
                <td>
                  @if ($o->consignment_id)
                    {{ $o->courier ?: 'Steadfast' }}<br><small class="ad-muted">{{ \App\Support\Steadfast::label($o->courier_status) }}</small>
                    @if ($o->courier_sent_by_name)<br><small class="ad-muted">sent by {{ $o->courier_sent_by_name }}</small>@endif
                  @else
                    <span class="ad-muted">—</span>
                  @endif
                </td>
              @endif
              <td class="r"><strong>৳ {{ number_format($o->total) }}</strong></td>
            </tr>
          @endforeach
        </table>
      </div>
      <div class="ad-pager">{{ $orders->links('pagination::simple-default') }}</div>
    @endif
  </section>
@endsection
