@extends('admin.layout')

@section('title', 'Orders')

@section('content')
  <h1 class="ad-title">Orders</h1>

  <div class="ad-tabs">
    <a href="{{ route('admin.orders.index') }}" class="{{ $status === '' ? 'on' : '' }}">All ({{ $counts->sum() }})</a>
    @foreach (\App\Models\Order::statuses() as $key => $label)
      <a href="{{ route('admin.orders.index', ['status' => $key]) }}" class="{{ $status === $key ? 'on' : '' }}">{{ $label }} ({{ $counts[$key] ?? 0 }})</a>
    @endforeach
  </div>

  <section class="ad-card">
    @if ($orders->isEmpty())
      <p class="ad-muted">No orders.</p>
    @else
      <div class="ad-scroll">
        <table class="ad-table">
          <tr><th>No.</th><th>Date</th><th>Customer</th><th>Mobile</th><th>Total</th><th>Status</th><th></th></tr>
          @foreach ($orders as $o)
            <tr>
              <td><a href="{{ route('admin.orders.show', $o->id) }}"><strong>#{{ $o->id }}</strong></a></td>
              <td>{{ $o->created_at->format('d M Y, h:i A') }}</td>
              <td>{{ $o->name }}</td>
              <td><a href="tel:{{ $o->phone }}">{{ $o->phone }}</a></td>
              <td>৳ {{ number_format($o->total) }}</td>
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
