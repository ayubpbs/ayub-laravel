@extends('admin.layout')

@section('title', 'Dashboard')

@section('content')
  <h1 class="ad-title">Dashboard</h1>

  <div class="ad-stats">
    <a class="ad-stat ad-stat-hot" href="{{ route('admin.orders.index', ['status' => 'pending']) }}">
      <span class="n">{{ $stats['pending'] }}</span><span class="l">New orders (to review)</span>
    </a>
    <div class="ad-stat"><span class="n">{{ $stats['today'] }}</span><span class="l">Today's orders</span></div>
    <div class="ad-stat"><span class="n">{{ $stats['orders'] }}</span><span class="l">Total orders</span></div>
    <div class="ad-stat"><span class="n">৳ {{ number_format($stats['delivered']) }}</span><span class="l">Delivered sales</span></div>
    @if ($stats['claim_orders'] > 0)
      <a class="ad-stat" href="{{ route('admin.orders.index', ['type' => 'claim']) }}"><span class="n">{{ $stats['claim_orders'] }}</span><span class="l">Exchange / replacement orders ({{ $stats['claim_orders_open'] }} open) — not counted as sales</span></a>
    @endif
    <a class="ad-stat" href="{{ route('admin.products.index') }}"><span class="n">{{ $stats['products'] }}</span><span class="l">Total products</span></a>
    @if ($stockSummary)
      <a class="ad-stat" href="{{ route('admin.products.index') }}"><span class="n">৳ {{ number_format($stockSummary['value']) }}</span><span class="l">Stock value (at buying price)</span></a>
    @endif
  </div>

  <div class="ad-two">
    <section class="ad-card">
      <div class="ad-card-head"><h2>Recent orders</h2><a href="{{ route('admin.orders.index') }}">View all →</a></div>
      @if ($recent->isEmpty())
        <p class="ad-muted">No orders yet.</p>
      @else
        <div class="ad-scroll">
          <table class="ad-table">
            <tr><th>No.</th><th>Name</th><th>Total</th><th>Status</th></tr>
            @foreach ($recent as $o)
              <tr>
                <td><a href="{{ route('admin.orders.show', $o->id) }}">#{{ $o->id }}</a></td>
                <td>{{ $o->name }}</td>
                <td>৳ {{ number_format($o->total) }}</td>
                <td><span class="ad-st st-{{ $o->status }}">{{ $o->statusLabel() }}</span></td>
              </tr>
            @endforeach
          </table>
        </div>
      @endif
    </section>

    <section class="ad-card">
      <div class="ad-card-head"><h2>Low / out of stock</h2></div>
      @if ($lowStock->isEmpty())
        <p class="ad-muted">All products have enough stock.</p>
      @else
        <div class="ad-scroll">
          <table class="ad-table">
            <tr><th>Product</th><th>Stock</th></tr>
            @foreach ($lowStock as $p)
              <tr>
                <td><a href="{{ route('admin.products.edit', $p->id) }}">{{ $p->title }}</a></td>
                <td class="{{ $p->stock_quantity < 1 ? 'ad-red' : '' }}">{{ $p->stock_quantity }}</td>
              </tr>
            @endforeach
          </table>
        </div>
      @endif
    </section>
  </div>
@endsection
