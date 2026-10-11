@extends('layouts.app')

@php
  $shopName = config('shop.name');
  $url      = $product->url();
  $gallery  = $product->galleryUrls();          // main image first, then the extra images
  $videos   = $product->videos();
  $img      = $gallery[0] ?? ($videos[0]['hq'] ?? null);   // used for social-media previews
  $state    = $product->stockState();
  $buyable  = in_array($state, ['in', 'low'], true);
  $metaDesc = $product->summary(160) ?: ($product->title . ' — ৳ ' . number_format($product->price) . ' at ' . $shopName);

  // Structured data for search engines (only facts that exist in the database)
  $ld = [
      '@context'    => 'https://schema.org',
      '@type'       => 'Product',
      'name'        => $product->title,
      'description' => $metaDesc,
      'url'         => $url,
      'offers'      => [
          '@type'         => 'Offer',
          'url'           => $url,
          'priceCurrency' => 'BDT',
          'price'         => (string) (int) $product->price,
          'availability'  => $state === 'upcoming' ? 'https://schema.org/PreOrder'
                           : ($buyable ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock'),
      ],
  ];
  if ($product->sku)   { $ld['sku'] = $product->sku; }
  if ($gallery)        { $ld['image'] = $gallery; }
  elseif ($img)        { $ld['image'] = [$img]; }
  if ($product->brand) { $ld['brand'] = ['@type' => 'Brand', 'name' => $product->brand]; }
  if ($specs = $product->specs()) {
      $ld['additionalProperty'] = array_map(function ($r) {
          return ['@type' => 'PropertyValue', 'name' => $r['name'], 'value' => $r['value']];
      }, $specs);
  }
  if ((int) $product->reviews > 0 && (float) $product->rating > 0) {
      $ld['aggregateRating'] = [
          '@type'       => 'AggregateRating',
          'ratingValue' => (string) $product->rating,
          'reviewCount' => (int) $product->reviews,
      ];
  }
@endphp

@section('title', $product->title . ' — ' . $shopName)

@push('head')
  <meta name="description" content="{{ $metaDesc }}">
  <link rel="canonical" href="{{ $url }}">
  <meta property="og:type" content="product">
  <meta property="og:site_name" content="{{ $shopName }}">
  <meta property="og:title" content="{{ $product->title }}">
  <meta property="og:description" content="{{ $metaDesc }}">
  <meta property="og:url" content="{{ $url }}">
  @if ($img)<meta property="og:image" content="{{ $img }}">@endif
  <meta property="product:price:amount" content="{{ (int) $product->price }}">
  <meta property="product:price:currency" content="BDT">
  <link rel="stylesheet" href="{{ asset('assets/css/product.css') }}">
  <script type="application/ld+json">{!! json_encode($ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
@endpush

@push('scripts')
  <script src="{{ asset('assets/js/product.js') }}" defer></script>
@endpush

@section('content')
  <section class="section pd-page">
    <nav class="crumbs" aria-label="Breadcrumb">
      <a href="{{ route('home') }}">Home</a>
      @foreach ($crumbs as $c)
        › <a href="{{ route('category.show', $c->id) }}">{{ $c->label }}</a>
      @endforeach
      › <span aria-current="page">{{ $product->title }}</span>
    </nav>

    <div class="pd">
      @include('shop.product._gallery')
      @include('shop.product._buybox')
    </div>

    @include('shop.product._tabs')
  </section>

  @if ($related->isNotEmpty())
    <section class="section">
      <div class="section-head"><h2>You may also like</h2></div>
      <div class="grid">
        @foreach ($related as $p) @include('shop._card') @endforeach
      </div>
    </section>
  @endif

  @if ($buyable)
    {{-- Mobile-only bar: appears when the main buy buttons scroll out of view --}}
    <div class="pd-bar" data-pd-bar>
      <div class="pd-bar-price">৳ {{ number_format($product->price) }}</div>
      <button type="submit" form="pd-buy-form" class="btn-outline pd-btn">🛒 Add to Cart</button>
      <button type="submit" form="pd-buy-form" name="buy_now" value="1" class="btn-primary pd-btn">Buy Now</button>
    </div>
  @endif

  <div class="pd-lightbox" data-pd-lightbox hidden role="dialog" aria-modal="true" aria-label="Product images">
    <button type="button" class="pd-lightbox-close" data-pd-close aria-label="Close">×</button>
    @if (count($gallery) > 1)
      <button type="button" class="pd-lb-nav pd-lb-prev" data-pd-lb-prev aria-label="Previous image">‹</button>
      <button type="button" class="pd-lb-nav pd-lb-next" data-pd-lb-next aria-label="Next image">›</button>
    @endif
    <img src="" alt="{{ $product->title }}">
  </div>
@endsection
