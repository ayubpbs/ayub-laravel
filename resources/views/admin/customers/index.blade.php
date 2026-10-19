@extends('admin.layout')

@section('title', 'Customers')

@section('content')
  <div class="ad-bar">
    <h1 class="ad-title">Customers</h1>
    <a class="ad-btn" href="{{ route('admin.customers.create') }}">+ Add customer</a>
  </div>

  <div class="ad-stats">
    <div class="ad-stat"><span class="n">{{ $summary['customers'] }}</span><span class="l">Total customers</span></div>
    <div class="ad-stat"><span class="n">{{ $summary['repeat'] }}</span><span class="l">Repeat customers (2+ orders)</span></div>
    <div class="ad-stat"><span class="n">{{ $summary['new_month'] }}</span><span class="l">New this month</span></div>
  </div>

  <form class="ad-filter" method="GET" action="{{ route('admin.customers.index') }}">
    <input type="text" name="q" value="{{ $q }}" placeholder="Search by mobile, name or order number (#123)…">
    <select name="sort">
      <option value="recent" {{ $sort === 'recent' ? 'selected' : '' }}>Latest order first</option>
      <option value="orders" {{ $sort === 'orders' ? 'selected' : '' }}>Most orders</option>
      <option value="spent"  {{ $sort === 'spent'  ? 'selected' : '' }}>Highest spending</option>
    </select>
    <button type="submit" class="ad-btn ad-btn-gray">Search</button>
    @if ($q !== '')<a class="ad-btn ad-btn-gray" href="{{ route('admin.customers.index') }}">Clear</a>@endif
  </form>

  @if ($canCreate)
    <section class="ad-card">
      <p>No customer found with the number <strong>{{ $norm }}</strong>.</p>
      <a class="ad-btn" href="{{ route('admin.customers.create', ['phone' => $norm]) }}">Add this customer</a>
    </section>
  @endif

  @if ($others->isNotEmpty())
    <section class="ad-card">
      <h2>Also found</h2>
      <div class="ad-scroll">
        <table class="ad-table">
          <tr><th>Customer</th><th>Mobile</th><th>Where</th><th>Orders</th><th></th></tr>
          @foreach ($others as $o)
            <tr>
              <td><a href="{{ route('admin.customers.show', $o['phone']) }}"><strong>{{ $o['name'] ?: '—' }}</strong></a></td>
              <td>{{ $o['phone'] }}</td>
              <td>{{ $o['label'] }}</td>
              <td>{{ $o['orders'] }}</td>
              <td><a class="ad-link" href="{{ route('admin.customers.show', $o['phone']) }}">View</a></td>
            </tr>
          @endforeach
        </table>
      </div>
    </section>
  @endif

  @if (!$canCreate)
  <section class="ad-card">
    @if ($customers->isEmpty())
      <p class="ad-muted">{{ $q !== '' ? 'No customers with orders match your search.' : 'No customers yet. They appear here once an order is placed.' }}</p>
    @else
      <div class="ad-scroll">
        <table class="ad-table">
          <tr><th>Customer</th><th>Mobile</th><th>Orders</th><th>Delivered total</th><th>Cancelled / Returned</th><th>Last order</th><th></th></tr>
          @foreach ($customers as $c)
            @php $l = $latest->get($c->last_order_id); @endphp
            <tr>
              <td>
                <a href="{{ route('admin.customers.show', $c->phone) }}"><strong>{{ $l->name ?? '—' }}</strong></a>
                @if ($c->orders_count > 1)<span class="ad-st st-confirmed">Repeat</span>@endif
              </td>
              <td><a href="tel:{{ $c->phone }}">{{ $c->phone }}</a></td>
              <td>{{ $c->orders_count }}</td>
              <td>৳ {{ number_format($c->delivered_total) }}</td>
              <td class="{{ $c->failed_count > 0 ? 'ad-red' : '' }}">{{ $c->failed_count }}</td>
              <td>{{ \Illuminate\Support\Carbon::parse($c->last_order_at)->format('d M Y') }}</td>
              <td><a class="ad-link" href="{{ route('admin.customers.show', $c->phone) }}">View</a></td>
            </tr>
          @endforeach
        </table>
      </div>
      <div class="ad-pager">{{ $customers->links('pagination::simple-default') }}</div>
    @endif
  </section>
  @endif
@endsection
