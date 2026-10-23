<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', config('shop.name') . ' — ' . config('shop.tagline'))</title>
  <link rel="stylesheet" href="{{ asset('assets/css/shop.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/nav.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/footer.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/mobile-nav.css') }}">
  @stack('head')
</head>
<body>

<header class="site-header">
  <div class="header-inner">
    <a class="logo" href="{{ route('home') }}">⚡ {{ config('shop.name') }}</a>
    <form class="search-form" action="{{ route('category.index') }}" method="GET">
      <input type="text" name="q" value="{{ request('q') }}" placeholder="Search products…">
      <button type="submit">Search</button>
    </form>
    <a class="track-link" href="{{ route('track.show') }}" title="Track your order" aria-label="Track your order">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 3h13v13H1z"/><path d="M14 8h4l4 4v4h-8z"/><circle cx="5.5" cy="18.5" r="2"/><circle cx="17.5" cy="18.5" r="2"/></svg>
      <span class="track-text">Track Order</span>
    </a>
    <a class="cart-link" href="{{ route('cart.index') }}">🛒 Cart
      <span class="cart-count" id="cart-count" @if (\App\Support\Cart::count() < 1) hidden @endif>{{ \App\Support\Cart::count() }}</span>
    </a>
  </div>
  @include('partials.header-nav')
</header>

<main class="container">
  @if (session('success'))
    <div class="flash flash-ok">{{ session('success') }}</div>
  @endif
  @if (session('error'))
    <div class="flash flash-err">{{ session('error') }}</div>
  @endif

  @yield('content')
</main>

@include('partials.footer')
@include('partials.mobile-bottom-nav')

<div class="toast" id="toast" hidden></div>
<script src="{{ asset('assets/js/shop.js') }}"></script>
<script src="{{ asset('assets/js/nav.js') }}"></script>
@stack('scripts')
</body>
</html>
