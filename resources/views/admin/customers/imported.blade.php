@extends('admin.layout')

@section('title', 'Old Customers')

@section('content')
  <div class="ad-bar">
    <h1 class="ad-title">Old Customers</h1>
  </div>

  <div class="ad-stats">
    <div class="ad-stat"><span class="n">{{ $summary['customers'] }}</span><span class="l">Total customers</span></div>
    <div class="ad-stat"><span class="n">{{ $summary['repeat'] }}</span><span class="l">Repeat customers (2+ orders)</span></div>
  </div>

  <form class="ad-filter" method="GET" action="{{ route('admin.imported-customers.index') }}">
    <input type="text" name="q" value="{{ $q }}" placeholder="Search by name, mobile or address…">
    <select name="sort">
      <option value="recent" {{ $sort === 'recent' ? 'selected' : '' }}>Latest order first</option>
      <option value="orders" {{ $sort === 'orders' ? 'selected' : '' }}>Most orders</option>
    </select>
    <button type="submit" class="ad-btn ad-btn-gray">Search</button>
    @if ($q !== '')<a class="ad-btn ad-btn-gray" href="{{ route('admin.imported-customers.index') }}">Clear</a>@endif
  </form>

  <section class="ad-card">
    @if ($customers->isEmpty())
      <p class="ad-muted">{{ $q !== '' ? 'No customers match your search.' : 'No customers found.' }}</p>
    @else
      <div class="ad-scroll">
        <table class="ad-table">
          <tr><th>Customer</th><th>Mobile</th><th>Address</th><th>Orders</th><th>First order</th><th>Last order</th></tr>
          @foreach ($customers as $c)
            <tr>
              <td>
                <strong>{{ $c->name ?: '—' }}</strong>
                @if ($c->total_orders > 1)<span class="ad-st st-confirmed">Repeat</span>@endif
              </td>
              <td><a href="tel:{{ $c->phone }}">{{ $c->phone }}</a></td>
              <td>{{ trim(implode(', ', array_filter([$c->address, $c->area, $c->zone, $c->city]))) }}</td>
              <td>{{ $c->total_orders }}</td>
              <td>{{ $c->first_order ? $c->first_order->format('d M Y') : '—' }}</td>
              <td>{{ $c->last_order ? $c->last_order->format('d M Y') : '—' }}</td>
            </tr>
          @endforeach
        </table>
      </div>
      <div class="ad-pager">{{ $customers->links('pagination::simple-default') }}</div>
    @endif
  </section>
@endsection
