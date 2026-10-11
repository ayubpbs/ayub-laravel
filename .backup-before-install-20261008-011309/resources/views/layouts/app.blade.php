<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', config('shop.name') . ' — ' . config('shop.tagline'))</title>
  <link rel="stylesheet" href="{{ asset('assets/css/shop.css') }}">
</head>
<body>

<header class="site-header">
  <div class="header-inner">
    <a class="logo" href="{{ route('home') }}">⚡ {{ config('shop.name') }}</a>
    <form class="search-form" action="{{ route('category.index') }}" method="GET">
      <input type="text" name="q" value="{{ request('q') }}" placeholder="Search products…">
      <button type="submit">Search</button>
    </form>
    <a class="cart-link" href="{{ route('cart.index') }}">🛒 Cart
      <span class="cart-count" id="cart-count" @if (\App\Support\Cart::count() < 1) hidden @endif>{{ \App\Support\Cart::count() }}</span>
    </a>
  </div>
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

<footer class="site-footer" id="contact">
  <p>© {{ date('Y') }} {{ config('shop.name') }}</p>
  @if (config('shop.phone'))
    <p>📞 <a href="tel:{{ config('shop.phone') }}">{{ config('shop.phone') }}</a></p>
  @endif
</footer>

<div class="toast" id="toast" hidden></div>
<script src="{{ asset('assets/js/shop.js') }}"></script>
@stack('scripts')
</body>
</html>
