@extends('admin.layout')

@section('title', $latest->name)

@section('content')
  <p class="ad-crumbs"><a href="{{ route('admin.customers.index') }}">← All customers</a></p>
  <h1 class="ad-title">{{ $latest->name }}</h1>

  <div class="ad-stats">
    <div class="ad-stat"><span class="n">{{ $stats['orders'] }}</span><span class="l">Total orders</span></div>
    <div class="ad-stat"><span class="n">{{ $stats['delivered'] }}</span><span class="l">Delivered</span></div>
    <div class="ad-stat {{ $stats['failed'] > 0 ? 'ad-stat-hot' : '' }}"><span class="n">{{ $stats['failed'] }}</span><span class="l">Cancelled / Returned</span></div>
    <div class="ad-stat"><span class="n">৳ {{ number_format($stats['spent']) }}</span><span class="l">Delivered total</span></div>
  </div>

  <div class="ad-two">
    <section class="ad-card">
      <h2>Details</h2>
      <dl class="ad-dl">
        <dt>Mobile</dt><dd><a href="tel:{{ $phone }}">{{ $phone }}</a></dd>
        <dt>Latest address</dt><dd>{{ $latest->address }}</dd>
        <dt>First order</dt><dd>{{ $stats['first']->format('d M Y') }}</dd>
        <dt>Last order</dt><dd>{{ $latest->created_at->format('d M Y, h:i A') }}</dd>
        @if ($names->count() > 1)
          <dt>Names used</dt><dd>{{ $names->implode(', ') }}</dd>
        @endif
      </dl>
    </section>

    <section class="ad-card">
      <h2>Addresses used</h2>
      <ul style="margin:0;padding-left:18px">
        @foreach ($addresses as $a)<li>{{ $a }}</li>@endforeach
      </ul>
    </section>
  </div>

  <section class="ad-card">
    <h2>Order history</h2>
    <div class="ad-scroll">
      <table class="ad-table">
        <tr><th>No.</th><th>Date</th><th>Total</th><th>Status</th><th></th></tr>
        @foreach ($orders as $o)
          <tr>
            <td><a href="{{ route('admin.orders.show', $o->id) }}"><strong>#{{ $o->id }}</strong></a></td>
            <td>{{ $o->created_at->format('d M Y, h:i A') }}</td>
            <td>৳ {{ number_format($o->total) }}</td>
            <td><span class="ad-st st-{{ $o->status }}">{{ $o->statusLabel() }}</span></td>
            <td><a class="ad-link" href="{{ route('admin.orders.show', $o->id) }}">View</a></td>
          </tr>
        @endforeach
      </table>
    </div>
  </section>
@endsection
