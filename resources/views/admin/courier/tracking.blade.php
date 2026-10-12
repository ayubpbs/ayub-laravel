@extends('admin.layout')

@section('title', 'Courier Tracking')

@section('content')
  <div class="ad-bar">
    <h1 class="ad-title">Tracking</h1>
    <form method="POST" action="{{ route('admin.courier.refresh.all') }}">
      @csrf
      <button type="submit" class="ad-btn" {{ $configured && $activeCount ? '' : 'disabled' }}>⟳ Refresh active parcels ({{ $activeCount }})</button>
    </form>
  </div>

  <form class="ad-filter" method="GET" action="{{ route('admin.courier.tracking') }}">
    <input type="text" name="q" value="{{ $q }}" placeholder="Search order no., name, mobile, consignment or tracking code…">
    <select name="cs">
      <option value="">All courier statuses</option>
      @foreach (\App\Support\Steadfast::statuses() as $key => $label)
        <option value="{{ $key }}" {{ $cs === $key ? 'selected' : '' }}>{{ $label }}</option>
      @endforeach
    </select>
    <select name="by">
      <option value="">Sent by: anyone</option>
      @foreach ($senders as $who)
        <option value="{{ $who }}" {{ $by === $who ? 'selected' : '' }}>{{ $who }}</option>
      @endforeach
    </select>
    <button type="submit" class="ad-btn ad-btn-gray">Search</button>
    @if ($q !== '' || $cs !== '' || $by !== '')<a class="ad-btn ad-btn-gray" href="{{ route('admin.courier.tracking') }}">Clear</a>@endif
  </form>

  <section class="ad-card">
    @if ($orders->isEmpty())
      <p class="ad-muted">No parcels found. Orders appear here once you send them to Steadfast.</p>
    @else
      <div class="ad-scroll">
        <table class="ad-table">
          <tr><th>Order</th><th>Customer</th><th>Consignment</th><th>Tracking code</th><th>Courier status</th><th>Order status</th><th>Sent by</th><th>Last checked</th><th></th></tr>
          @foreach ($orders as $o)
            <tr>
              <td><a href="{{ route('admin.orders.show', $o->id) }}"><strong>#{{ $o->id }}</strong></a></td>
              <td>{{ $o->name }}<br><small class="ad-muted">{{ $o->phone }}</small></td>
              <td>{{ $o->consignment_id }}</td>
              <td>{{ $o->tracking_code ?: '—' }}</td>
              <td><span class="ad-st {{ \App\Support\Steadfast::badge($o->courier_status) }}">{{ \App\Support\Steadfast::label($o->courier_status) }}</span></td>
              <td><span class="ad-st st-{{ $o->status }}">{{ $o->statusLabel() }}</span></td>
              <td>{{ $o->courier_sent_by_name ?: '—' }}</td>
              <td>{{ $o->courier_checked_at ? $o->courier_checked_at->diffForHumans() : 'never' }}</td>
              <td>
                <form action="{{ route('admin.courier.refresh', $o->id) }}" method="POST">
                  @csrf
                  <button type="submit" class="ad-link" {{ $configured ? '' : 'disabled' }}>Refresh</button>
                </form>
              </td>
            </tr>
          @endforeach
        </table>
      </div>
      <div class="ad-pager">{{ $orders->links('pagination::simple-default') }}</div>
    @endif
  </section>

  <p class="ad-hint">When Steadfast reports a parcel as "Delivered", the order is moved to Delivered automatically. Other courier statuses are shown here only — change the order status yourself if needed (for example after a partial delivery).</p>
@endsection
