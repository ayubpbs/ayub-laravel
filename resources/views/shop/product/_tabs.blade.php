{{-- Description / Details / Delivery & Warranty. Without JavaScript every panel is simply shown one after another. --}}
@php
  $warrantyOn    = \App\Models\Setting::bool('warranty_enabled', false);
  $warrantyDays  = (int) \App\Models\Setting::get('warranty_days', 0);
  $warrantyTerms = trim((string) \App\Models\Setting::get('warranty_terms', ''));
  $rates         = \App\Support\Cart::rates();
  $specs         = $product->specs();
  $categoryPath  = collect($crumbs)->pluck('label')->implode(' › ');
@endphp

<div class="pd-tabs" id="reviews" data-pd-tabs>
  <div class="pd-tab-nav" role="tablist" aria-label="Product information">
    <button type="button" role="tab" id="pd-tab-desc" aria-controls="pd-panel-desc" aria-selected="true">Description</button>
    @if ($specs)
      <button type="button" role="tab" id="pd-tab-specs" aria-controls="pd-panel-specs" aria-selected="false" tabindex="-1">Specifications</button>
    @endif
    <button type="button" role="tab" id="pd-tab-details" aria-controls="pd-panel-details" aria-selected="false" tabindex="-1">Details</button>
    <button type="button" role="tab" id="pd-tab-ship" aria-controls="pd-panel-ship" aria-selected="false" tabindex="-1">Delivery &amp; Warranty</button>
    @if (\App\Models\ProductReview::available())
      <button type="button" role="tab" id="pd-tab-reviews" aria-controls="pd-panel-reviews" aria-selected="false" tabindex="-1">Reviews @if ((int) $product->reviews > 0)({{ (int) $product->reviews }})@endif</button>
    @endif
  </div>

  <div class="pd-panel" id="pd-panel-desc" role="tabpanel" aria-labelledby="pd-tab-desc">
    <h3>Description</h3>
    @if ($product->hasDescription())
      <div class="pd-prose">{!! $product->descriptionHtml() !!}</div>
    @else
      <p class="pd-muted">No description has been added for this product yet.</p>
    @endif
  </div>

  @if ($specs)
    <div class="pd-panel" id="pd-panel-specs" role="tabpanel" aria-labelledby="pd-tab-specs">
      <h3>Specifications</h3>
      <table class="pd-table">
        <tbody>
          @foreach ($specs as $row)
            <tr><th scope="row">{{ $row['name'] }}</th><td>{{ $row['value'] }}</td></tr>
          @endforeach
        </tbody>
      </table>
    </div>
  @endif

  <div class="pd-panel" id="pd-panel-details" role="tabpanel" aria-labelledby="pd-tab-details">
    <h3>Details</h3>
    <table class="pd-table">
      <tbody>
        <tr><th scope="row">Product</th><td>{{ $product->title }}</td></tr>
        @if ($product->sku)<tr><th scope="row">SKU</th><td>{{ $product->sku }}</td></tr>@endif
        @if ($product->brand)<tr><th scope="row">Brand</th><td>{{ $product->brand }}</td></tr>@endif
        @if ($categoryPath !== '')<tr><th scope="row">Category</th><td>{{ $categoryPath }}</td></tr>@endif
        <tr><th scope="row">Availability</th><td>
          @if ($state === 'upcoming') Coming soon
          @elseif ($state === 'out') Out of stock
          @elseif ($state === 'low') Low stock ({{ $product->stock_quantity }} left)
          @else In stock
          @endif
        </td></tr>
        @if ((int) $product->reviews > 0)<tr><th scope="row">Rating</th><td>{{ $product->rating }} / 5 ({{ $product->reviews }})</td></tr>@endif
      </tbody>
    </table>
  </div>

  <div class="pd-panel" id="pd-panel-ship" role="tabpanel" aria-labelledby="pd-tab-ship">
    <h3>Delivery &amp; Warranty</h3>
    <p><strong>Delivery:</strong> {{ $rates['inside'] > 0 || $rates['outside'] > 0 ? 'Delivery charge is ৳ ' . number_format($rates['inside']) . ' inside Dhaka and ৳ ' . number_format($rates['outside']) . ' outside Dhaka, per order.' : 'No delivery charge.' }}</p>
    @if ($warrantyOn && $warrantyDays > 0)
      <p><strong>Warranty:</strong> {{ $warrantyDays }} days.</p>
    @endif
    @if ($warrantyOn && $warrantyTerms !== '')
      <div class="pd-prose">{!! nl2br(e($warrantyTerms)) !!}</div>
    @endif
  </div>

  @if (\App\Models\ProductReview::available())
    <div class="pd-panel" id="pd-panel-reviews" role="tabpanel" aria-labelledby="pd-tab-reviews">
      <h3>Reviews</h3>
      @include('shop.product._reviews')
    </div>
  @endif
</div>
