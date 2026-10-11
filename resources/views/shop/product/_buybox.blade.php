{{-- Title, price, stock, buy form, contact and trust strip. Expects: $product, $url, $state, $buyable --}}
@php
  $max      = max(1, (int) $product->stock_quantity);
  $phone    = trim((string) config('shop.phone'));
  $waMsg    = "Hello, I'm interested in: {$product->title} ({$url})";
  $waLink   = \App\Models\Setting::whatsappLink($waMsg);
  if (!$waLink) {
      $waNum = preg_replace('/\D+/', '', (string) config('shop.whatsapp_number'));
      $waLink = $waNum !== '' ? 'https://wa.me/' . $waNum . '?text=' . rawurlencode($waMsg) : null;
  }
  $rates        = \App\Support\Cart::rates();
  $warrantyOn   = \App\Models\Setting::bool('warranty_enabled', false);
  $warrantyDays = (int) \App\Models\Setting::get('warranty_days', 0);
@endphp

<div class="pd-info">
  @if ($product->brand)
    <a class="pd-brand" href="{{ route('category.index', ['q' => $product->brand]) }}">{{ $product->brand }}</a>
  @endif
  <h1 class="pd-title">{{ $product->title }}</h1>

  <div class="pd-rating">
    @if ((int) $product->reviews > 0)
      <span class="stars" aria-hidden="true">{{ $product->stars() }}</span>
      <a href="#reviews" data-pd-goto-reviews>{{ $product->rating }} · {{ $product->reviews }} {{ $product->reviews == 1 ? 'review' : 'reviews' }}</a>
    @elseif (\App\Models\ProductReview::available())
      <a class="pd-muted" href="#reviews" data-pd-goto-reviews>No reviews yet — write the first one</a>
    @else
      <span class="pd-muted">No reviews yet</span>
    @endif
  </div>

  <div class="pd-price">
    <span class="pd-price-now">৳ {{ number_format($product->price) }}</span>
    @if ($product->discountPercent() > 0)
      <span class="pd-price-old">৳ {{ number_format($product->old_price) }}</span>
      <span class="pd-save">Save ৳ {{ number_format($product->savings()) }} ({{ $product->discountPercent() }}%)</span>
    @endif
  </div>

  <p class="pd-stock pd-stock-{{ $state }}">
    @if ($state === 'upcoming') ⏳ Coming soon
    @elseif ($state === 'out') ✘ Out of stock
    @elseif ($state === 'low') ⚠ Only {{ $product->stock_quantity }} left in stock
    @else ✔ In stock
    @endif
  </p>

  @if ($product->hasShortDescription())
    <div class="pd-short pd-prose">{!! $product->shortDescriptionHtml() !!}</div>
  @elseif ($product->summary(220) !== '')
    <p class="pd-summary">{{ $product->summary(220) }}</p>
  @endif

  @if ($buyable)
    <form id="pd-buy-form" class="buy-form pd-buy" action="{{ route('cart.add', $product->id) }}" method="POST">
      @csrf
      <div class="pd-qty-row">
        <span class="pd-label" id="pd-qty-label">Quantity</span>
        <div class="pd-qty" data-pd-qty data-max="{{ $max }}" data-price="{{ (int) $product->price }}" role="group" aria-labelledby="pd-qty-label">
          <button type="button" data-step="-1" aria-label="Decrease quantity">−</button>
          <input type="number" name="quantity" value="1" min="1" max="{{ $max }}" inputmode="numeric" aria-label="Quantity">
          <button type="button" data-step="1" aria-label="Increase quantity">+</button>
        </div>
        <span class="pd-total">Total: <strong>৳ <span data-pd-total>{{ number_format($product->price) }}</span></strong></span>
      </div>

      <div class="buy-actions pd-actions" data-pd-actions>
        <button type="submit" class="btn-outline pd-btn">🛒 Add to Cart</button>
        <button type="submit" name="buy_now" value="1" class="btn-primary pd-btn">Buy Now</button>
      </div>
    </form>
  @elseif ($state === 'upcoming')
    <p class="note">This product is coming soon. Contact us to ask about availability.</p>
  @else
    <p class="note">Sorry, this product is currently out of stock. Contact us to ask when it will be back.</p>
  @endif

  @if ($waLink || $phone !== '')
    <div class="pd-contact">
      @if ($phone !== '')
        <a class="btn-small pd-contact-btn" href="tel:{{ $phone }}">📞 Call {{ $phone }}</a>
      @endif
      @if ($waLink)
        <a class="btn-small pd-contact-btn" href="{{ $waLink }}" target="_blank" rel="noopener noreferrer">💬 Ask on WhatsApp</a>
      @endif
    </div>
  @endif

  <ul class="pd-trust">
    <li>🚚 {{ $rates['inside'] > 0 || $rates['outside'] > 0 ? 'Delivery ৳ ' . number_format($rates['inside']) . ' inside Dhaka · ৳ ' . number_format($rates['outside']) . ' outside Dhaka' : 'Free delivery' }}</li>
    @if ($warrantyOn && $warrantyDays > 0)
      <li>🛡️ {{ $warrantyDays }}-day warranty</li>
    @endif
  </ul>
</div>
