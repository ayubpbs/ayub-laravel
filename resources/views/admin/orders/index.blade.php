@extends('admin.layout')

@section('title', 'Orders')

@section('content')
  @php $heading = $status === '' ? 'All Orders' : \App\Models\Order::statuses()[$status] . ' Orders'; @endphp
  <div class="ad-bar">
    <h1 class="ad-title">{{ $heading }}</h1>
    <form method="GET" action="{{ route('admin.orders.index') }}" class="ad-filter" style="margin:0">
      @if ($status !== '')<input type="hidden" name="status" value="{{ $status }}">@endif
      <input type="text" name="q" value="{{ $search }}" placeholder="Search name, mobile or order no.">
      <input type="text" name="product" value="{{ $product }}" placeholder="Product">
      <select name="type">
        <option value="">All order types</option>
        <option value="normal" {{ $type === 'normal' ? 'selected' : '' }}>Normal orders (sales)</option>
        <option value="claim" {{ $type === 'claim' ? 'selected' : '' }}>Exchange + replacement</option>
        <option value="replacement" {{ $type === 'replacement' ? 'selected' : '' }}>Replacement only</option>
        <option value="exchange" {{ $type === 'exchange' ? 'selected' : '' }}>Exchange only</option>
      </select>
      <input type="date" name="from" value="{{ $from }}" title="From date">
      <input type="date" name="to" value="{{ $to }}" title="To date">
      <button type="submit" class="ad-btn">Search</button>
      @if ($search !== '' || $product !== '' || $type !== '' || $from !== '' || $to !== '')<a class="ad-btn ad-btn-gray" href="{{ route('admin.orders.index', $status !== '' ? ['status' => $status] : []) }}">Clear</a>@endif
    </form>
  </div>

  <div class="ad-tabs">
    <a href="{{ route('admin.orders.index') }}" class="{{ $status === '' ? 'on' : '' }}">All ({{ $counts->sum() }})</a>
    @foreach (\App\Models\Order::statuses() as $key => $label)
      <a href="{{ route('admin.orders.index', ['status' => $key]) }}" class="{{ $status === $key ? 'on' : '' }}">{{ $label }} ({{ $counts[$key] ?? 0 }})</a>
    @endforeach
  </div>

  <section class="ad-card">
    @if ($orders->isEmpty())
      <p class="ad-muted">{{ ($search !== '' || $product !== '' || $type !== '' || $from !== '' || $to !== '') ? 'No orders match your search.' : 'No orders.' }}</p>
    @else
      <div class="ad-scroll">
        <table class="ad-table">
          <tr><th>No.</th><th>Date</th><th>Customer</th><th>Mobile</th><th>Total</th><th>Payment</th><th>Status</th><th></th></tr>
          @foreach ($orders as $o)
            <tr>
              <td>
                <a href="{{ route('admin.orders.show', $o->id) }}"><strong>#{{ $o->id }}</strong></a>
                @if ($o->isClaimOrder())<br><span class="ad-st st-courier_sent">{{ $o->typeLabel() }}</span>@endif
              </td>
              <td>{{ $o->created_at->format('d M Y, h:i A') }}</td>
              <td>{{ $o->name }}</td>
              <td><a href="tel:{{ $o->phone }}">{{ $o->phone }}</a></td>
              <td>৳ {{ number_format($o->total) }}</td>
              <td><span class="ad-st {{ \App\Models\Order::paymentBadge($o->paymentStatus()) }}">{{ $o->paymentLabel() }}</span></td>
              <td><span class="ad-st st-{{ $o->status }}">{{ $o->statusLabel() }}</span></td>
              <td><a class="ad-link" href="{{ route('admin.orders.show', $o->id) }}">View</a></td>
            </tr>
          @endforeach
        </table>
      </div>
      <div class="ad-pager">{{ $orders->links('pagination::simple-default') }}</div>
    @endif
  </section>
@endsection
