<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>@yield('title', 'Admin') — {{ config('shop.name') }}</title>
  <link rel="stylesheet" href="{{ asset('assets/css/admin.css') }}">
</head>
<body>
@php $pending = \App\Models\Order::where('status', 'pending')->count(); @endphp
<header class="ad-header">
  <div class="ad-header-inner">
    <a class="ad-logo" href="{{ route('admin.dashboard') }}">⚙️ {{ config('shop.name') }} <small>Admin</small></a>
    <nav class="ad-nav">
      <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'on' : '' }}">Dashboard</a>
      <a href="{{ route('admin.orders.index') }}" class="{{ request()->routeIs('admin.orders.*') ? 'on' : '' }}">
        Orders @if ($pending > 0)<span class="ad-pill">{{ $pending }}</span>@endif
      </a>
      <a href="{{ route('admin.products.index') }}" class="{{ request()->routeIs('admin.products.*') ? 'on' : '' }}">Products</a>
      <a href="{{ route('admin.categories.index') }}" class="{{ request()->routeIs('admin.categories.*') ? 'on' : '' }}">Categories</a>
      <a href="{{ route('home') }}" target="_blank">View site ↗</a>
    </nav>
    <form action="{{ route('admin.logout') }}" method="POST">
      @csrf
      <button type="submit" class="ad-logout">Logout</button>
    </form>
  </div>
</header>

<main class="ad-main">
  @if (session('success'))
    <div class="ad-flash ad-ok">{{ session('success') }}</div>
  @endif
  @if (session('error'))
    <div class="ad-flash ad-err">{{ session('error') }}</div>
  @endif
  @if ($errors->any())
    <div class="ad-flash ad-err">
      @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
  @endif

  @yield('content')
</main>
</body>
</html>
