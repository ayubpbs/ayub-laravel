@extends('layouts.app')

@section('title', $product->title . ' — ' . config('shop.name'))

@section('content')
  <section class="section">
    <p class="crumbs">
      <a href="{{ route('home') }}">Home</a>
      @if ($product->category)
        › <a href="{{ route('category.show', $product->category->id) }}">{{ $product->category->label }}</a>
      @endif
    </p>

    <div class="product-page">
      <div class="product-page-media">
        @if ($product->image)
          <img src="{{ asset('uploads/products/' . $product->image) }}" alt="{{ $product->title }}">
        @else
          <span class="emoji">{{ $product->icon ?: '📦' }}</span>
        @endif
      </div>

      <div class="product-page-info">
        <span class="product-brand">{{ $product->brand }}</span>
        <h1>{{ $product->title }}</h1>
        <div class="rating"><span class="stars">{{ $product->stars() }}</span> {{ $product->rating }} ({{ $product->reviews }} reviews)</div>

        <div class="price-row big">
          <span class="price-now">৳ {{ number_format($product->price) }}</span>
          @if ($product->old_price)
            <span class="price-old">৳ {{ number_format($product->old_price) }}</span>
          @endif
        </div>

        <p class="stock {{ $product->inStock() ? 'in' : 'out' }}">
          @if ($product->is_upcoming) Coming soon
          @elseif ($product->inStock()) ✔ In stock ({{ $product->stock_quantity }})
          @else ✘ Out of stock
          @endif
        </p>

        @if ($product->description)
          <p class="desc">{{ $product->description }}</p>
        @endif

        @if ($product->is_upcoming)
          <p class="note">This product is coming soon.</p>
        @elseif ($product->inStock())
          <form class="buy-form" action="{{ route('cart.add', $product->id) }}" method="POST">
            @csrf
            <label class="qty-label">Quantity
              <input type="number" name="quantity" value="1" min="1" max="{{ $product->stock_quantity }}">
            </label>
            <div class="buy-actions">
              <button type="submit" class="btn-outline">🛒 Add to Cart</button>
              <button type="submit" name="buy_now" value="1" class="btn-primary">Buy Now</button>
            </div>
          </form>
        @else
          <p class="note">Sorry, this product is currently out of stock.</p>
        @endif
      </div>
    </div>
  </section>

  @if ($related->isNotEmpty())
    <section class="section">
      <div class="section-head"><h2>More from this category</h2></div>
      <div class="grid">
        @foreach ($related as $p) @include('shop._card') @endforeach
      </div>
    </section>
  @endif
@endsection
