<div class="ad-tabs">
  <a href="{{ route('admin.reports.daily') }}" class="{{ request()->routeIs('admin.reports.daily') ? 'on' : '' }}">Daily Sales</a>
  <a href="{{ route('admin.reports.monthly') }}" class="{{ request()->routeIs('admin.reports.monthly') ? 'on' : '' }}">Monthly Sales</a>
  <a href="{{ route('admin.reports.products') }}" class="{{ request()->routeIs('admin.reports.products') ? 'on' : '' }}">Product-wise Sales</a>
  <a href="{{ route('admin.reports.profit') }}" class="{{ request()->routeIs('admin.reports.profit') ? 'on' : '' }}">Profit / Loss</a>
  @foreach (['delivered' => 'Delivered', 'cancelled' => 'Cancelled', 'returned' => 'Returned'] as $k => $l)
    <a href="{{ route('admin.reports.orders', $k) }}" class="{{ request()->routeIs('admin.reports.orders') && request()->route('status') === $k ? 'on' : '' }}">{{ $l }} Orders</a>
  @endforeach
</div>
