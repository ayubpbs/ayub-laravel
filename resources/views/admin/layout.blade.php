<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>@yield('title', 'Admin') — {{ config('shop.name') }}</title>
  <link rel="stylesheet" href="{{ asset('assets/css/admin.css') }}">
</head>
<body class="ad-body">
<div class="ad-shell">
  @include('admin.partials.sidebar')

  <div class="ad-content">
    <div class="ad-topbar">
      <button type="button" class="ad-burger" id="sbToggle" aria-label="Menu">☰</button>
      <span class="ad-topbar-title">{{ config('shop.name') }}</span>
      @php $bellCount = \App\Models\AdminNotification::unreadCount(); @endphp
      <a class="ad-topbar-bell" href="{{ route('admin.notifications.index') }}" aria-label="Notifications">🔔@if ($bellCount > 0)<span class="sb-badge">{{ $bellCount }}</span>@endif</a>
    </div>

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
  </div>
</div>

<script>
  (function () {
    var body = document.body;
    function toggle(open) { body.classList.toggle('sb-open', open); }
    document.getElementById('sbToggle').addEventListener('click', function () { toggle(!body.classList.contains('sb-open')); });
    document.getElementById('sbOverlay').addEventListener('click', function () { toggle(false); });
  })();
</script>
</body>
</html>
