@extends('admin.layout')

@section('title', 'Website Settings')

@section('content')
  <div class="ad-bar">
    <h1 class="ad-title">⚙️ Website Settings</h1>
  </div>

  <div class="ad-tabs st-jump">
    <a href="#st-shop">Shop</a>
    <a href="#st-delivery">Delivery &amp; Advance</a>
    <a href="#st-warranty">Warranty</a>
    <a href="#st-stock">Stock display</a>
    <a href="#st-invoice">Invoice</a>
    <a href="#st-social">Social links</a>
    <a href="#st-business">Business info</a>
  </div>

  <form method="POST" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data" class="ad-form">
    @csrf

    {{-- ===== Shop: name, logo, phone, WhatsApp ===== --}}
    <section class="ad-card" id="st-shop">
      <h2>🏪 Shop</h2>
      <div class="ad-form">
        <div class="ad-row">
          <label>Shop name *
            <input type="text" name="shop_name" value="{{ $v['shop_name'] }}" maxlength="100" required>
          </label>
          <label>Tagline <small>(short line under the name)</small>
            <input type="text" name="tagline" value="{{ $v['tagline'] }}" maxlength="150">
          </label>
        </div>

        <div class="st-field">
          <span class="st-label">Logo <small>JPG, PNG or WebP · up to 2 MB</small></span>
          @if ($logoUrl)
            <span class="ad-current">
              <img src="{{ $logoUrl }}" alt="Current logo">
              <span class="ad-check st-remove">
                <input type="checkbox" name="remove_logo" value="1" id="remove_logo">
                <label for="remove_logo" class="st-inline">Remove current logo</label>
              </span>
            </span>
          @endif
          <input type="file" name="logo" accept="image/png,image/jpeg,image/webp">
        </div>

        <div class="ad-row">
          <label>Phone
            <input type="text" name="phone" value="{{ $v['phone'] }}" inputmode="tel" placeholder="01XXXXXXXXX" maxlength="20">
          </label>
          <label>WhatsApp number
            <input type="text" name="whatsapp" value="{{ $v['whatsapp'] }}" inputmode="tel" placeholder="01XXXXXXXXX" maxlength="20">
          </label>
        </div>
      </div>
    </section>

    {{-- ===== Delivery charge + advance ===== --}}
    <section class="ad-card" id="st-delivery">
      <h2>🚚 Delivery &amp; Advance</h2>
      <div class="ad-row">
        <label>Delivery charge — Inside Dhaka (৳) *
          <input type="number" name="delivery_inside_dhaka" value="{{ $v['delivery_inside_dhaka'] }}" min="0" max="100000" step="1" required>
          <small>Used when the customer's district is Dhaka.</small>
        </label>
        <label>Delivery charge — Outside Dhaka (৳) *
          <input type="number" name="delivery_outside_dhaka" value="{{ $v['delivery_outside_dhaka'] }}" min="0" max="100000" step="1" required>
          <small>Used for every other district.</small>
        </label>
        <label>Advance amount (৳) *
          <input type="number" name="advance_amount" value="{{ $v['advance_amount'] }}" min="0" max="10000000" step="1" required>
          <small>Amount a customer pays upfront. Use 0 if no advance is needed.</small>
        </label>
      </div>
    </section>

    {{-- ===== Warranty ===== --}}
    <section class="ad-card" id="st-warranty">
      <h2>🛡️ Warranty settings</h2>
      <div class="ad-form">
        <input type="hidden" name="warranty_enabled" value="0">
        <label class="ad-check"><input type="checkbox" name="warranty_enabled" value="1" {{ $v['warranty_enabled'] ? 'checked' : '' }}> Offer warranty on products</label>

        <div class="ad-row">
          <label>Default warranty period (days)
            <input type="number" name="warranty_days" value="{{ $v['warranty_days'] }}" min="0" max="3650" step="1">
            <small>e.g. 30 = one month, 365 = one year. 0 = none.</small>
          </label>
        </div>
        <label>Warranty terms
          <textarea name="warranty_terms" rows="3" maxlength="1000" placeholder="e.g. Warranty does not cover physical damage or burn.">{{ $v['warranty_terms'] }}</textarea>
        </label>
      </div>
    </section>

    {{-- ===== Stock display ===== --}}
    <section class="ad-card" id="st-stock">
      <h2>📦 Stock display</h2>
      <div class="ad-form">
        <input type="hidden" name="show_stock_count" value="0">
        <label class="ad-check"><input type="checkbox" name="show_stock_count" value="1" {{ $v['show_stock_count'] ? 'checked' : '' }}> Show the number of units in stock to customers (home page, product cards and product page)</label>
        <small>Stock goes down automatically when an order is placed and comes back when an order is cancelled or returned. Change the number on the product's edit page when new goods arrive.</small>
      </div>
    </section>

    {{-- ===== Invoice ===== --}}
    <section class="ad-card" id="st-invoice">
      <h2>📄 Invoice settings</h2>
      <div class="ad-form">
        <label>Footer note
          <input type="text" name="invoice_note" value="{{ $v['invoice_note'] }}" maxlength="300">
        </label>
        <label>Terms &amp; conditions <small>(printed at the bottom of every invoice)</small>
          <textarea name="invoice_terms" rows="3" maxlength="1000">{{ $v['invoice_terms'] }}</textarea>
        </label>

        <div class="ad-checks">
          <input type="hidden" name="invoice_show_logo" value="0">
          <label class="ad-check"><input type="checkbox" name="invoice_show_logo" value="1" {{ $v['invoice_show_logo'] ? 'checked' : '' }}> Show logo on invoice</label>
          <input type="hidden" name="invoice_show_phone" value="0">
          <label class="ad-check"><input type="checkbox" name="invoice_show_phone" value="1" {{ $v['invoice_show_phone'] ? 'checked' : '' }}> Show shop phone on invoice</label>
          <input type="hidden" name="invoice_show_warranty" value="0">
          <label class="ad-check"><input type="checkbox" name="invoice_show_warranty" value="1" {{ $v['invoice_show_warranty'] ? 'checked' : '' }}> Show warranty terms on invoice</label>
        </div>
      </div>
    </section>

    {{-- ===== Social links ===== --}}
    <section class="ad-card" id="st-social">
      <h2>🔗 Social links</h2>
      <div class="ad-row">
        @foreach ($socials as $key => [$label, $icon])
          <label>{{ $icon }} {{ $label }}
            <input type="text" name="{{ $key }}" value="{{ $v[$key] }}" inputmode="url" placeholder="https://" maxlength="255">
          </label>
        @endforeach
      </div>
      <p class="ad-hint">Leave a field empty to hide that link.</p>
    </section>

    {{-- ===== Business information ===== --}}
    <section class="ad-card" id="st-business">
      <h2>🏢 Business information</h2>
      <div class="ad-form">
        <label>Address
          <textarea name="address" rows="2" maxlength="300">{{ $v['address'] }}</textarea>
        </label>
        <div class="ad-row">
          <label>Email
            <input type="text" name="email" value="{{ $v['email'] }}" inputmode="email" maxlength="150">
          </label>
          <label>Opening hours
            <input type="text" name="business_hours" value="{{ $v['business_hours'] }}" maxlength="150" placeholder="e.g. Sat–Thu, 10 AM – 9 PM">
          </label>
        </div>
        <div class="ad-row">
          <label>Trade license no.
            <input type="text" name="trade_license" value="{{ $v['trade_license'] }}" maxlength="60">
          </label>
          <label>BIN / TIN
            <input type="text" name="bin_tin" value="{{ $v['bin_tin'] }}" maxlength="60">
          </label>
        </div>
        <label>About the business
          <textarea name="about" rows="3" maxlength="1000">{{ $v['about'] }}</textarea>
        </label>
      </div>
    </section>

    <div class="ad-submit st-save">
      <button type="submit" class="ad-btn">💾 Save settings</button>
    </div>
  </form>
@endsection
