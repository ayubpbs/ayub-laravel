{{-- Mobile bottom navigation bar (visible only on screens <= 760px, styled in assets/css/mobile-nav.css) --}}
@php
  $mbnCount = \App\Support\Cart::count();
@endphp
<nav class="mbn" aria-label="Quick navigation">
  <a href="{{ route('home') }}" class="mbn-item {{ request()->routeIs('home') ? 'is-active' : '' }}">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/><path d="M10 20v-6h4v6"/></svg>
    <span>Home</span>
  </a>
  <a href="{{ route('category.index') }}" class="mbn-item {{ request()->routeIs('category.*') ? 'is-active' : '' }}">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
    <span>Shop</span>
  </a>
  <a href="{{ route('cart.index') }}" class="mbn-item {{ request()->routeIs('cart.*', 'checkout.*') ? 'is-active' : '' }}">
    <span class="mbn-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.7 13.4a2 2 0 0 0 2 1.6h9.7a2 2 0 0 0 2-1.6L23 6H6"/></svg>
      <span class="mbn-badge" id="mbn-cart-count" @if ($mbnCount < 1) hidden @endif>{{ $mbnCount }}</span>
    </span>
    <span>Cart</span>
  </a>
  <a href="{{ route('track.show') }}" class="mbn-item {{ request()->routeIs('track.*') ? 'is-active' : '' }}">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 3h13v13H1z"/><path d="M14 8h4l4 4v4h-8z"/><circle cx="5.5" cy="18.5" r="2"/><circle cx="17.5" cy="18.5" r="2"/></svg>
    <span>Track</span>
  </a>
</nav>

<script>
  // Keep the bottom-bar cart badge in sync with the header cart badge (updated by shop.js after add-to-cart)
  (function () {
    var src = document.getElementById('cart-count');
    var dst = document.getElementById('mbn-cart-count');
    if (!src || !dst) return;
    function sync() {
      dst.textContent = src.textContent;
      dst.hidden = src.hidden;
    }
    new MutationObserver(sync).observe(src, { childList: true, characterData: true, subtree: true, attributes: true });
    sync();
  })();
</script>
