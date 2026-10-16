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

    {{-- topbar sub-navigation + breadcrumb (must come after the sidebar include above) --}}
    @include('admin.partials.subnav')

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

    // Clicking a collapsed main category opens that module's first page, where its sub-navigation shows in the topbar.
    // An open category just collapses as before. Ctrl/Cmd/Shift-click and categories marked data-no-land keep the old behaviour.
    document.querySelectorAll('.sb-nav > details.sb-group > summary').forEach(function (sum) {
      sum.addEventListener('click', function (e) {
        var group = sum.parentNode;
        if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey || group.open || sum.classList.contains('on') || sum.hasAttribute('data-no-land')) { return; }
        var first = group.querySelector('.sb-sub > a.sb-sublink[href]');
        if (first) { e.preventDefault(); window.location.href = first.getAttribute('href'); }
      });
    });

    // Bring the active topbar item into view when the row scrolls horizontally
    document.querySelectorAll('.ad-subnav-row').forEach(function (row) {
      var on = row.querySelector('.ad-subnav-link.on');
      if (on) { row.scrollLeft = Math.max(0, on.offsetLeft - (row.clientWidth - on.offsetWidth) / 2); }
    });
  })();
</script>
</body>
</html>
