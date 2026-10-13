@php
  $hasOld = !empty($p->old_price);
  $inStock = $p->inStock();
@endphp
<div class="product-card">
  <a href="{{ $p->url() }}" class="product-media">
    @if ($p->is_upcoming)
      <span class="badge" style="background:#b8860b;">Upcoming..</span>
    @elseif (!$inStock)
      <span class="badge" style="background:#c0392b;">Stock out</span>
    @elseif ($p->badge)
      <span class="badge {{ $hasOld ? 'badge-discount' : 'badge-new' }}">{{ $p->badge }}</span>
    @endif

    @if ($p->image)
      <img src="{{ asset('uploads/products/' . $p->image) }}" alt="{{ $p->title }}" loading="lazy">
    @else
      <span class="emoji">{{ $p->icon ?: '📦' }}</span>
    @endif
  </a>
  <div class="product-body">
    <span class="product-brand">{{ $p->brand }}</span>
    <a href="{{ $p->url() }}" class="product-title">{{ $p->title }}</a>
    <div class="rating"><span class="stars">{{ $p->stars() }}</span> {{ $p->rating }} ({{ $p->reviews }})</div>
    @if ($inStock && !$p->is_upcoming && \App\Models\Product::showStockCount())
      <div class="card-stock {{ $p->stockState() === 'low' ? 'card-stock-low' : '' }}">Stock: {{ number_format((int) $p->stock_quantity) }}</div>
    @endif
    <div class="price-row">
      <span class="price-now">৳ {{ number_format($p->price) }}</span>
      @if ($hasOld)
        <span class="price-old">৳ {{ number_format($p->old_price) }}</span>
      @endif
    </div>
    @if ($p->is_upcoming)
      <span class="card-disabled">Coming soon</span>
    @elseif (!$inStock)
      <span class="card-disabled">Out of stock</span>
    @else
      <form class="card-actions" action="{{ route('cart.add', $p->id) }}" method="POST">
        @csrf
        <input type="hidden" name="quantity" value="1">
        <button type="submit" class="btn-cart">🛒 Add to Cart</button>
        <button type="submit" name="buy_now" value="1" class="btn-buy">Buy Now</button>
      </form>
    @endif
  </div>
</div>
