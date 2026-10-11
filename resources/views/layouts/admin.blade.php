<!DOCTYPE html>
<html lang="bn">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', 'অ্যাডমিন') — {{ config('shop.name') }}</title>
  <link rel="stylesheet" href="{{ asset('assets/css/admin.css') }}">
</head>
<body>
<div class="adm-wrap">
  <aside class="adm-side">
    <div class="adm-brand">⚡ {{ config('shop.name') }}<small>অ্যাডমিন প্যানেল</small></div>
    <nav>
      <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'on' : '' }}">📊 ড্যাশবোর্ড</a>
      <a href="{{ route('admin.products.index') }}" class="{{ request()->routeIs('admin.products.*') ? 'on' : '' }}">📦 প্রোডাক্ট</a>
      <a href="{{ route('admin.categories.index') }}" class="{{ request()->routeIs('admin.categories.*') ? 'on' : '' }}">🗂 ক্যাটাগরি</a>
      <span class="soon">🧾 অর্ডার (পরের ধাপে)</span>
      <a href="{{ route('home') }}" target="_blank">🌐 সাইট দেখুন</a>
    </nav>
    <form method="POST" action="{{ route('admin.logout') }}" class="adm-logout">
      @csrf
      <button type="submit">⏻ লগআউট ({{ auth()->user()->name }})</button>
    </form>
  </aside>

  <main class="adm-main">
    @if (session('success')) <div class="alert ok">{{ session('success') }}</div> @endif
    @if (session('error'))   <div class="alert err">{{ session('error') }}</div> @endif
    @if ($errors->any())
      <div class="alert err">
        <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
      </div>
    @endif

    @yield('content')
  </main>
</div>
</body>
</html>
