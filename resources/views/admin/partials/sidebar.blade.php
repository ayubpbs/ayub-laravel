@php

    // Order counts per status (one query) for the badges
    $oc        = \App\Models\Order::select('status', \Illuminate\Support\Facades\DB::raw('count(*) as c'))->groupBy('status')->pluck('c', 'status');
    $readyCount = $oc['confirmed'] ?? 0; // confirmed orders waiting for the courier (close enough for a badge)
    // Live orders that still have money to collect (for the Payments badge)
    $dueOrders = \App\Models\Order::whereNotIn('status', \App\Models\Order::lockedStatuses())->where('status', 'delivered')->owing()->count();
    $unreadNotif = \App\Models\AdminNotification::unreadCount();
    $curStatus = request()->routeIs('admin.orders.index') ? (string) request('status', '') : null;

    $orderChildren = [['label' => 'All Orders', 'url' => route('admin.orders.index'), 'on' => $curStatus === '' || ($curStatus !== null && !array_key_exists($curStatus, \App\Models\Order::statuses())), 'count' => $oc->sum()]];
    foreach (\App\Models\Order::statuses() as $key => $label) {
        $orderChildren[] = [
            'label' => $label,
            'url'   => route('admin.orders.index', ['status' => $key]),
            'on'    => $curStatus === $key,
            'count' => $oc[$key] ?? 0,
            'hot'   => $key === 'pending',
        ];
    }

    // The full admin structure. Items with no 'url' are not built yet and show as "Soon".
    $menu = [
        ['label' => 'Dashboard', 'icon' => '📊', 'url' => route('admin.dashboard'), 'on' => request()->routeIs('admin.dashboard')],
        ['label' => 'Orders', 'icon' => '🧾', 'on' => request()->routeIs('admin.orders.*'), 'badge' => $oc['pending'] ?? 0, 'children' => $orderChildren],
        ['label' => 'Products', 'icon' => '📦', 'on' => request()->routeIs('admin.products.*') || request()->routeIs('admin.categories.*') || request()->routeIs('admin.brands.*') || request()->routeIs('admin.reviews.*'), 'badge' => \App\Models\ProductReview::pendingCount(), 'children' => [
            ['label' => 'All Products', 'url' => route('admin.products.index'), 'on' => request()->routeIs('admin.products.index') || request()->routeIs('admin.products.edit')],
            ['label' => 'Add Product',  'url' => route('admin.products.create'), 'on' => request()->routeIs('admin.products.create')],
            ['label' => 'Categories',   'url' => route('admin.categories.index'), 'on' => request()->routeIs('admin.categories.*')],
            ['label' => 'Brands',       'url' => route('admin.brands.index'), 'on' => request()->routeIs('admin.brands.*')],
            ['label' => 'Reviews',      'url' => route('admin.reviews.index'), 'on' => request()->routeIs('admin.reviews.*'), 'count' => \App\Models\ProductReview::pendingCount(), 'hot' => true],
        ]],
        ['label' => 'Customers', 'icon' => '👥', 'url' => route('admin.customers.index'), 'on' => request()->routeIs('admin.customers.*')],
        ['label' => 'Coupons', 'icon' => '🎟️', 'url' => route('admin.coupons.index'), 'on' => request()->routeIs('admin.coupons.*')],
        ['label' => 'Courier', 'icon' => '🚚', 'on' => request()->routeIs('admin.courier.*'), 'children' => [
            ['label' => 'Steadfast', 'url' => route('admin.courier.steadfast'), 'on' => request()->routeIs('admin.courier.steadfast') || request()->routeIs('admin.courier.send*'), 'count' => $readyCount, 'hot' => true],
            ['label' => 'Tracking',  'url' => route('admin.courier.tracking'),  'on' => request()->routeIs('admin.courier.tracking') || request()->routeIs('admin.courier.refresh*')],
            ['label' => 'Returns',   'url' => route('admin.courier.returns'),   'on' => request()->routeIs('admin.courier.returns*')],
        ]],
        ['label' => 'Payments', 'icon' => '💳', 'url' => route('admin.payments.index'), 'on' => request()->routeIs('admin.payments.*'), 'badge' => $dueOrders],
        ['label' => 'Invoices', 'icon' => '📄', 'url' => route('admin.invoices.index'), 'on' => request()->routeIs('admin.invoices.*')],
        ['label' => 'Reports', 'icon' => '📈', 'on' => request()->routeIs('admin.reports.*'), 'children' => [
            ['label' => 'Daily Sales',         'url' => route('admin.reports.daily'),    'on' => request()->routeIs('admin.reports.daily')],
            ['label' => 'Monthly Sales',       'url' => route('admin.reports.monthly'),  'on' => request()->routeIs('admin.reports.monthly')],
            ['label' => 'Product-wise Sales',  'url' => route('admin.reports.products'), 'on' => request()->routeIs('admin.reports.products')],
            ['label' => 'Profit / Loss',       'url' => route('admin.reports.profit'),   'on' => request()->routeIs('admin.reports.profit')],
            ['label' => 'Delivered Orders',    'url' => route('admin.reports.orders', 'delivered'), 'on' => request()->routeIs('admin.reports.orders') && request()->route('status') === 'delivered'],
            ['label' => 'Cancelled Orders',    'url' => route('admin.reports.orders', 'cancelled'), 'on' => request()->routeIs('admin.reports.orders') && request()->route('status') === 'cancelled'],
            ['label' => 'Returned Orders',     'url' => route('admin.reports.orders', 'returned'),  'on' => request()->routeIs('admin.reports.orders') && request()->route('status') === 'returned'],
        ]],
        ['label' => 'Accounting', 'icon' => '💰', 'view' => 'admin.partials.accounting-menu'],
        ['label' => 'Staff & Permissions', 'icon' => '🛡️'],
        ['label' => 'Notifications', 'icon' => '🔔', 'url' => route('admin.notifications.index'), 'on' => request()->routeIs('admin.notifications.*'), 'badge' => $unreadNotif],
        ['label' => 'Settings', 'icon' => '⚙️', 'url' => route('admin.settings.edit'), 'on' => request()->routeIs('admin.settings.*')],
    ];
@endphp

<aside class="sb" id="sidebar">
  <a class="sb-brand" href="{{ route('admin.dashboard') }}">
    @php $sbLogo = \App\Models\Setting::logoUrl(); @endphp
    <span class="sb-logo {{ $sbLogo ? 'has-img' : '' }}">@if ($sbLogo)<img src="{{ $sbLogo }}" alt="">@else⚙️@endif</span>
    <span>{{ config('shop.name') }}<small>Admin Panel</small></span>
  </a>

  <nav class="sb-nav">
    @foreach ($menu as $item)
      @if (!empty($item['view']))
        @include($item['view'])
      @elseif (!empty($item['children']))
        <details class="sb-group" {{ !empty($item['on']) ? 'open' : '' }}>
          <summary class="sb-link {{ !empty($item['on']) ? 'on' : '' }}">
            <span class="sb-ico">{{ $item['icon'] }}</span>
            <span class="sb-text">{{ $item['label'] }}</span>
            @if (!empty($item['badge']))<span class="sb-badge">{{ $item['badge'] }}</span>@endif
            <span class="sb-caret">›</span>
          </summary>
          <div class="sb-sub">
            @foreach ($item['children'] as $c)
              @if (!empty($c['url']))
                <a class="sb-sublink {{ !empty($c['on']) ? 'on' : '' }}" href="{{ $c['url'] }}">
                  <span class="sb-text">{{ $c['label'] }}</span>
                  @if (isset($c['count']))<span class="sb-count {{ !empty($c['hot']) && $c['count'] > 0 ? 'hot' : '' }}">{{ $c['count'] }}</span>@endif
                </a>
              @else
                <span class="sb-sublink is-soon" title="Coming soon"><span class="sb-text">{{ $c['label'] }}</span><span class="sb-soon">Soon</span></span>
              @endif
            @endforeach
          </div>
        </details>
      @elseif (!empty($item['url']))
        <a class="sb-link {{ !empty($item['on']) ? 'on' : '' }}" href="{{ $item['url'] }}">
          <span class="sb-ico">{{ $item['icon'] }}</span><span class="sb-text">{{ $item['label'] }}</span>
          @if (!empty($item['badge']))<span class="sb-badge">{{ $item['badge'] }}</span>@endif
        </a>
      @else
        <span class="sb-link is-soon" title="Coming soon">
          <span class="sb-ico">{{ $item['icon'] }}</span><span class="sb-text">{{ $item['label'] }}</span><span class="sb-soon">Soon</span>
        </span>
      @endif
    @endforeach
  </nav>

  <div class="sb-foot">
    <a href="{{ route('home') }}" target="_blank">View site ↗</a>
    <form action="{{ route('admin.logout') }}" method="POST">
      @csrf
      <button type="submit">Logout</button>
    </form>
  </div>
</aside>
<div class="sb-overlay" id="sbOverlay"></div>
