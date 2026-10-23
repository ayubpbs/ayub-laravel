@php

    // Order counts per status (one query) for the badges
    $oc        = \App\Models\Order::select('status', \Illuminate\Support\Facades\DB::raw('count(*) as c'))->groupBy('status')->pluck('c', 'status');
    $readyCount = $oc['confirmed'] ?? 0; // confirmed orders waiting for the courier (close enough for a badge)
    // Live orders that still have money to collect (for the Payments badge)
    $dueOrders = \App\Models\Order::whereNotIn('status', \App\Models\Order::lockedStatuses())->where('status', 'delivered')->owing()->count();
    $unreadNotif = \App\Models\AdminNotification::unreadCount();
    // Phase 5: open claims (0 until the migration has been run)
    $claimTables = \App\Models\ServiceRequest::tableReady();
    $claimOpen   = $claimTables ? \App\Models\ServiceRequest::where('status', 'open')->count() : 0;
    $claimStatus = request()->routeIs('admin.claims.index') ? (string) request('status', '') : null;
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
    if (\App\Support\AdminAccess::can('orders_create')) {
        $orderChildren[] = ['label' => '+ New Order', 'url' => route('admin.orders.create'), 'on' => request()->routeIs('admin.orders.create') || request()->routeIs('admin.orders.store')];
    }

    // The full admin structure. Items with no 'url' are not built yet and show as "Soon".
    $menu = [
        ['perm' => 'dashboard', 'label' => 'Dashboard', 'icon' => '📊', 'url' => route('admin.dashboard'), 'on' => request()->routeIs('admin.dashboard')],
        ['perm' => 'orders_view', 'label' => 'Orders', 'icon' => '🧾', 'on' => request()->routeIs('admin.orders.*'), 'badge' => $oc['pending'] ?? 0, 'children' => $orderChildren],
        ['perm' => 'claims_view', 'label' => 'Claims', 'icon' => '↩️', 'on' => request()->routeIs('admin.claims.*'), 'badge' => $claimOpen, 'children' => array_values(array_filter([
            ['label' => 'All Claims', 'url' => route('admin.claims.index'), 'on' => $claimStatus === '' || request()->routeIs('admin.claims.show')],
            ['label' => 'Open',       'url' => route('admin.claims.index', ['status' => 'open']), 'on' => $claimStatus === 'open', 'count' => $claimOpen, 'hot' => true],
            ['label' => 'Approved',   'url' => route('admin.claims.index', ['status' => 'approved']), 'on' => $claimStatus === 'approved'],
            ['label' => 'Warranty claims', 'url' => route('admin.claims.index', ['kind' => 'warranty']), 'on' => request()->routeIs('admin.claims.index') && request('kind') === 'warranty'],
            \App\Support\AdminAccess::can('claims_manage') ? ['label' => '+ Warranty Claim', 'url' => route('admin.claims.warranty.create'), 'on' => request()->routeIs('admin.claims.warranty.*')] : null,
            \App\Support\AdminAccess::can('claims_manage') ? ['label' => '+ New Claim', 'url' => route('admin.claims.create'), 'on' => request()->routeIs('admin.claims.create') || request()->routeIs('admin.claims.store')] : null,
        ]))],
        ['perm' => 'products', 'label' => 'Products', 'icon' => '📦', 'on' => request()->routeIs('admin.products.*') || request()->routeIs('admin.categories.*') || request()->routeIs('admin.brands.*') || request()->routeIs('admin.reviews.*'), 'badge' => \App\Models\ProductReview::pendingCount(), 'children' => [
            ['label' => 'All Products', 'url' => route('admin.products.index'), 'on' => request()->routeIs('admin.products.index') || request()->routeIs('admin.products.edit')],
            ['label' => 'Add Product',  'url' => route('admin.products.create'), 'on' => request()->routeIs('admin.products.create')],
            ['label' => 'Categories',   'url' => route('admin.categories.index'), 'on' => request()->routeIs('admin.categories.*')],
            ['label' => 'Brands',       'url' => route('admin.brands.index'), 'on' => request()->routeIs('admin.brands.*')],
            ['label' => 'Reviews',      'url' => route('admin.reviews.index'), 'on' => request()->routeIs('admin.reviews.*'), 'count' => \App\Models\ProductReview::pendingCount(), 'hot' => true],
        ]],
        ['perm' => 'customers', 'label' => 'Customers', 'icon' => '👥', 'url' => route('admin.customers.index'), 'on' => request()->routeIs('admin.customers.*')],
        ['perm' => 'owner', 'label' => 'Old Customers', 'icon' => '🗂️', 'url' => route('admin.imported-customers.index'), 'on' => request()->routeIs('admin.imported-customers.*')],
        ['perm' => 'coupons', 'label' => 'Coupons', 'icon' => '🎟️', 'url' => route('admin.coupons.index'), 'on' => request()->routeIs('admin.coupons.*')],
        ['perm' => 'courier', 'label' => 'Courier', 'icon' => '🚚', 'on' => request()->routeIs('admin.courier.*'), 'children' => [
            ['label' => 'Steadfast', 'url' => route('admin.courier.steadfast'), 'on' => request()->routeIs('admin.courier.steadfast') || request()->routeIs('admin.courier.send*'), 'count' => $readyCount, 'hot' => true],
            ['label' => 'Tracking',  'url' => route('admin.courier.tracking'),  'on' => request()->routeIs('admin.courier.tracking') || request()->routeIs('admin.courier.refresh*')],
            ['label' => 'Returns',   'url' => route('admin.courier.returns'),   'on' => request()->routeIs('admin.courier.returns*')],
        ]],
        ['perm' => 'payments', 'label' => 'Payments', 'icon' => '💳', 'url' => route('admin.payments.index'), 'on' => request()->routeIs('admin.payments.*'), 'badge' => $dueOrders],
        ['perm' => 'invoices', 'label' => 'Invoices', 'icon' => '📄', 'url' => route('admin.invoices.index'), 'on' => request()->routeIs('admin.invoices.*')],
        ['perm' => 'reports', 'label' => 'Reports', 'icon' => '📈', 'land' => false, 'on' => request()->routeIs('admin.reports.*'), 'children' => [
            ['label' => 'Daily Sales',         'url' => route('admin.reports.daily'),    'on' => request()->routeIs('admin.reports.daily')],
            ['label' => 'Monthly Sales',       'url' => route('admin.reports.monthly'),  'on' => request()->routeIs('admin.reports.monthly')],
            ['label' => 'Product-wise Sales',  'url' => route('admin.reports.products'), 'on' => request()->routeIs('admin.reports.products')],
            ['label' => 'Profit / Loss',       'url' => route('admin.reports.profit'),   'on' => request()->routeIs('admin.reports.profit')],
            ['label' => 'Delivered Orders',    'url' => route('admin.reports.orders', 'delivered'), 'on' => request()->routeIs('admin.reports.orders') && request()->route('status') === 'delivered'],
            ['label' => 'Cancelled Orders',    'url' => route('admin.reports.orders', 'cancelled'), 'on' => request()->routeIs('admin.reports.orders') && request()->route('status') === 'cancelled'],
            ['label' => 'Returned Orders',     'url' => route('admin.reports.orders', 'returned'),  'on' => request()->routeIs('admin.reports.orders') && request()->route('status') === 'returned'],
        ]],
        ['perm' => 'accounting', 'label' => 'Accounting', 'icon' => '💰', 'view' => 'admin.partials.accounting-menu'],
        ['perm' => 'owner', 'label' => 'Audit Log', 'icon' => '📝', 'url' => \Illuminate\Support\Facades\Route::has('admin.audit.index') ? route('admin.audit.index') : null, 'on' => request()->routeIs('admin.audit.*')],
        ['perm' => 'owner', 'label' => 'Staff & Permissions', 'icon' => '🛡️', 'url' => \Illuminate\Support\Facades\Route::has('admin.staff.index') ? route('admin.staff.index') : null, 'on' => request()->routeIs('admin.staff.*')],
        ['perm' => 'orders_view', 'label' => 'Notifications', 'icon' => '🔔', 'url' => route('admin.notifications.index'), 'on' => request()->routeIs('admin.notifications.*'), 'badge' => $unreadNotif],
        ['perm' => 'owner', 'label' => 'Website Menu', 'icon' => '🧭', 'url' => route('admin.menu.index'), 'on' => request()->routeIs('admin.menu.*')],
        ['perm' => 'owner', 'label' => 'Settings', 'icon' => '⚙️', 'url' => route('admin.settings.edit'), 'on' => request()->routeIs('admin.settings.*')],
    ];

    // Staff only see the menus they were given. The owner sees everything.
    $menu = array_values(array_filter($menu, fn ($i) => \App\Support\AdminAccess::can($i['perm'] ?? 'owner')));
    // Hand the same (permission-filtered) menu to the topbar sub-navigation: admin/partials/subnav.blade.php
    \Illuminate\Support\Facades\View::share('adminNavMenu', $menu);
    $sbWho = \App\Support\AdminAccess::name();
    $sbOwner = \App\Support\AdminAccess::isOwner();
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
          <summary class="sb-link {{ !empty($item['on']) ? 'on' : '' }}" @if (($item['land'] ?? true) === false) data-no-land @endif>
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

  {{-- who is signed in (the .sb-foot below is one flex row, so this sits above it) --}}
  <div class="sb-who" style="padding:10px 16px 0;font-size:13px;color:#cbd5e1;border-top:1px solid #1f2937">
    👤 <strong style="color:#fff">{{ $sbWho }}</strong> <small style="opacity:.7">({{ $sbOwner ? 'Owner' : 'Staff' }})</small>
  </div>
  <div class="sb-foot" style="border-top:0">
    <a href="{{ route('home') }}" target="_blank">View site ↗</a>
    <form action="{{ route('admin.logout') }}" method="POST">
      @csrf
      <button type="submit">Logout</button>
    </form>
  </div>
</aside>
<div class="sb-overlay" id="sbOverlay"></div>
