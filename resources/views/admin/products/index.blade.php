@extends('admin.layout')

@section('title', 'Products')

@section('content')
  <div class="ad-bar">
    <h1 class="ad-title">All Products <small class="ad-muted" style="font-size:15px;font-weight:400">({{ $products->total() }})</small></h1>
    <a class="ad-btn" href="{{ route('admin.products.create') }}">＋ New Product</a>
  </div>

  @if ($summary)
    <div class="ad-stats">
      <div class="ad-stat ad-stat-hot"><span class="n">৳ {{ number_format($summary['value']) }}</span><span class="l">Stock value (at buying price)</span></div>
      <div class="ad-stat"><span class="n">{{ number_format($summary['units']) }}</span><span class="l">Units in stock</span></div>
      <div class="ad-stat"><span class="n">৳ {{ number_format($summary['retail']) }}</span><span class="l">Stock value (at selling price)</span></div>
    </div>
    @if ($summary['missing'] > 0)
      <p class="ad-hint" style="margin:0 0 12px">⚠ {{ $summary['missing'] }} product(s) have stock but no buying price, so they are counted as ৳ 0.
        <a href="{{ route('admin.products.index', ['cost' => 'missing']) }}">Show them</a> and enter their buying price.</p>
    @endif
  @endif

  <form class="ad-filter" method="GET" action="{{ route('admin.products.index') }}">
    @if ($cost === 'missing')<input type="hidden" name="cost" value="missing">@endif
    <input type="text" name="q" value="{{ $q }}" placeholder="Search by name, brand or SKU…">
    <select name="category">
      <option value="">All categories</option>
      @foreach ($categories as $c)
        <option value="{{ $c->id }}" {{ (string) $cat === (string) $c->id ? 'selected' : '' }}>{{ $c->depth ? '— ' : '' }}{{ $c->label }}</option>
      @endforeach
    </select>
    <select name="brand">
      <option value="">All brands</option>
      @foreach ($brands as $b)
        <option value="{{ $b->id }}" {{ (string) $brand === (string) $b->id ? 'selected' : '' }}>{{ $b->name }}</option>
      @endforeach
    </select>
    <select name="stock">
      <option value="">Any stock</option>
      <option value="in"  {{ $stock === 'in'  ? 'selected' : '' }}>In stock</option>
      <option value="low" {{ $stock === 'low' ? 'selected' : '' }}>Low stock (1–5)</option>
      <option value="out" {{ $stock === 'out' ? 'selected' : '' }}>Out of stock</option>
    </select>
    <button type="submit" class="ad-btn ad-btn-gray">Search</button>
    @if ($q !== '' || $cat !== '' || $brand !== '' || $stock !== '' || $cost !== '')
      <a class="ad-btn ad-btn-gray" href="{{ route('admin.products.index') }}">Clear</a>
    @endif
  </form>

  <section class="ad-card">
    @if ($products->isEmpty())
      <p class="ad-muted">No products found.</p>
    @else
      <div class="ad-scroll">
        <table class="ad-table">
          <tr><th>Image</th><th>Name</th><th>Category</th><th>Brand</th><th>Price</th>@if ($hasCost)<th>Buying price</th>@endif<th>Stock</th>@if ($hasCost)<th>Stock value</th>@endif<th>Status</th><th></th></tr>
          @foreach ($products as $p)
            <tr>
              <td class="ad-thumb">
                @if ($p->image)
                  <img src="{{ asset('uploads/products/' . $p->image) }}" alt="">
                @else
                  <span>{{ $p->icon ?: '📦' }}</span>
                @endif
              </td>
              <td><a href="{{ route('admin.products.edit', $p->id) }}"><strong>{{ $p->title }}</strong></a>@if ($p->sku)<br><small class="ad-muted">SKU: {{ $p->sku }}</small>@endif</td>
              <td>{{ optional($p->category)->label ?: '—' }}</td>
              <td>{{ $p->brand ?: '—' }}</td>
              <td>৳ {{ number_format($p->price) }}</td>
              @if ($hasCost)<td>{{ $p->cost_price !== null ? '৳ ' . number_format($p->cost_price) : '—' }}</td>@endif
              <td class="{{ $p->stock_quantity < 1 ? 'ad-red' : '' }}">{{ $p->stock_quantity }}</td>
              @if ($hasCost)<td>{{ $p->cost_price !== null ? '৳ ' . number_format($p->cost_price * $p->stock_quantity) : '—' }}</td>@endif
              <td>
                @if (!$p->is_published) <span class="ad-st st-cancelled">Draft</span>
                @elseif ($p->is_upcoming) <span class="ad-st st-shipped">Coming soon</span>
                @else <span class="ad-st st-delivered">Active</span>
                @endif
              </td>
              <td class="ad-actions">
                <a class="ad-link" href="{{ route('admin.products.edit', $p->id) }}">Edit</a>
                <form action="{{ route('admin.products.destroy', $p->id) }}" method="POST" onsubmit="return confirm('Really delete this product?');">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="ad-link ad-del">Delete</button>
                </form>
              </td>
            </tr>
          @endforeach
        </table>
      </div>
      <div class="ad-pager">{{ $products->links('pagination::simple-default') }}</div>
    @endif
  </section>
@endsection
