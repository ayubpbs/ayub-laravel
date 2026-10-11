@extends('admin.layout')

@section('title', 'Products')

@section('content')
  <div class="ad-bar">
    <h1 class="ad-title">All Products <small class="ad-muted" style="font-size:15px;font-weight:400">({{ $products->total() }})</small></h1>
    <a class="ad-btn" href="{{ route('admin.products.create') }}">＋ New Product</a>
  </div>

  <form class="ad-filter" method="GET" action="{{ route('admin.products.index') }}">
    <input type="text" name="q" value="{{ $q }}" placeholder="Search by name or brand…">
    <select name="category">
      <option value="">All categories</option>
      @foreach ($categories as $c)
        <option value="{{ $c->id }}" {{ (string) $cat === (string) $c->id ? 'selected' : '' }}>{{ $c->label }}</option>
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
    @if ($q !== '' || $cat !== '' || $brand !== '' || $stock !== '')
      <a class="ad-btn ad-btn-gray" href="{{ route('admin.products.index') }}">Clear</a>
    @endif
  </form>

  <section class="ad-card">
    @if ($products->isEmpty())
      <p class="ad-muted">No products found.</p>
    @else
      <div class="ad-scroll">
        <table class="ad-table">
          <tr><th>Image</th><th>Name</th><th>Category</th><th>Brand</th><th>Price</th><th>Stock</th><th>Status</th><th></th></tr>
          @foreach ($products as $p)
            <tr>
              <td class="ad-thumb">
                @if ($p->image)
                  <img src="{{ asset('uploads/products/' . $p->image) }}" alt="">
                @else
                  <span>{{ $p->icon ?: '📦' }}</span>
                @endif
              </td>
              <td><a href="{{ route('admin.products.edit', $p->id) }}"><strong>{{ $p->title }}</strong></a></td>
              <td>{{ optional($p->category)->label ?: '—' }}</td>
              <td>{{ $p->brand ?: '—' }}</td>
              <td>৳ {{ number_format($p->price) }}</td>
              <td class="{{ $p->stock_quantity < 1 ? 'ad-red' : '' }}">{{ $p->stock_quantity }}</td>
              <td>
                @if (!$p->is_published) <span class="ad-st st-cancelled">Hidden</span>
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
