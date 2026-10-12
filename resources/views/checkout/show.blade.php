@extends('layouts.app')

@section('title', 'Checkout — ' . config('shop.name'))

@push('head')
  <link rel="stylesheet" href="{{ asset('assets/css/checkout.css') }}">
@endpush

@php
  $oldDistrict = old('district', '');
  $oldUpazila  = old('upazila', '');
  $itemCount   = array_sum(array_column($lines, 'qty'));
  // Policy box is ticked by default; after a failed submit we respect what the customer chose
  $termsChecked = old() ? (bool) old('terms') : true;
  $payOld       = old('payment_method', 'cod') === 'bkash' ? 'bkash' : 'cod';

  // Everything the checkout script needs (district list, charges, current totals)
  $jsData = [
      'locations' => $locations,
      'rates'     => $rates,
      'subtotal'  => $subtotal,
      'discount'  => $discount,
      'district'  => $oldDistrict,
      'upazila'   => $oldUpazila,
  ];
@endphp

@section('content')
  {{-- Icons (used below with <use href="#i-name">) --}}
  <svg width="0" height="0" style="position:absolute" aria-hidden="true">
    <symbol id="i-user" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></symbol>
    <symbol id="i-phone" viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></symbol>
    <symbol id="i-pin" viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></symbol>
    <symbol id="i-home" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></symbol>
    <symbol id="i-note" viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></symbol>
    <symbol id="i-truck" viewBox="0 0 24 24"><rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></symbol>
    <symbol id="i-trash" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></symbol>
    <symbol id="i-tag" viewBox="0 0 24 24"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></symbol>
    <symbol id="i-check" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></symbol>
    <symbol id="i-lock" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></symbol>
    <symbol id="i-back" viewBox="0 0 24 24"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></symbol>
    <symbol id="i-x" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></symbol>
    <symbol id="i-minus" viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/></symbol>
    <symbol id="i-plus" viewBox="0 0 24 24"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></symbol>
    <symbol id="i-bag" viewBox="0 0 24 24"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></symbol>
  </svg>

  <section class="co">
    {{-- Progress --}}
    <ol class="co-steps" aria-label="Checkout progress">
      <li class="done"><span class="dot"><svg class="ico"><use href="#i-check"/></svg></span><a href="{{ route('cart.index') }}">Cart</a></li>
      <li class="on"><span class="dot">2</span>Checkout</li>
      <li><span class="dot">3</span>Complete</li>
    </ol>

    <div class="co-head">
      <div>
        <h1>Complete your order</h1>
        <p>Fill in your delivery details and review your items.</p>
      </div>
      <a class="co-back" href="{{ route('cart.index') }}"><svg class="ico"><use href="#i-back"/></svg> Back to cart</a>
    </div>

    @if ($errors->any())
      <div class="co-alert co-alert-err" role="alert">Please fix the highlighted fields below and try again.</div>
    @endif
    @if ($notice)
      <div class="co-alert co-alert-warn" role="status">{{ $notice }}</div>
    @endif

    <form id="checkout-form" class="co-grid" action="{{ route('checkout.store') }}" method="POST"
          data-qty-url="{{ route('checkout.qty', ['id' => 0]) }}"
          data-remove-url="{{ route('checkout.remove', ['id' => 0]) }}"
          data-coupon-url="{{ route('checkout.coupon') }}"
          data-coupon-remove-url="{{ route('checkout.coupon.remove') }}">
      @csrf

      <div class="co-main">
        {{-- 2. Delivery details --}}
        <div class="co-card">
          <div class="co-card-head">
            <span class="co-num"><svg class="ico"><use href="#i-truck"/></svg></span>
            <h2>Delivery details</h2>
          </div>

          <div class="co-fields">
            <div class="co-field @error('name') has-err @enderror">
              <label for="f-name">Name <b>*</b></label>
              <div class="co-input"><svg class="ico"><use href="#i-user"/></svg>
                <input id="f-name" type="text" name="name" value="{{ old('name') }}" required maxlength="100" autocomplete="name" placeholder="Your full name">
              </div>
              @error('name')<p class="co-err">{{ $message }}</p>@enderror
            </div>

            <div class="co-field @error('phone') has-err @enderror">
              <label for="f-phone">Mobile number <b>*</b></label>
              <div class="co-input"><svg class="ico"><use href="#i-phone"/></svg>
                <input id="f-phone" type="tel" name="phone" value="{{ old('phone') }}" required inputmode="tel" autocomplete="tel" placeholder="01XXXXXXXXX">
              </div>
              @error('phone')<p class="co-err">{{ $message }}</p>@enderror
            </div>

            <div class="co-field @error('district') has-err @enderror">
              <label for="f-district">District <b>*</b></label>
              <div class="co-input co-select"><svg class="ico"><use href="#i-pin"/></svg>
                <select id="f-district" name="district" required><option value="">Select district</option></select>
              </div>
              @error('district')<p class="co-err">{{ $message }}</p>@enderror
            </div>

            <div class="co-field @error('upazila') has-err @enderror">
              <label for="f-upazila">Upazila / Thana <b>*</b></label>
              <div class="co-input co-select"><svg class="ico"><use href="#i-pin"/></svg>
                <select id="f-upazila" name="upazila" required disabled><option value="">Select district first</option></select>
              </div>
              @error('upazila')<p class="co-err">{{ $message }}</p>@enderror
            </div>

            <div class="co-field @error('city') has-err @enderror">
              <label for="f-city">City / Town <b>*</b></label>
              <div class="co-input"><svg class="ico"><use href="#i-home"/></svg>
                <input id="f-city" type="text" name="city" value="{{ old('city') }}" required maxlength="100" autocomplete="address-level2" placeholder="e.g. Dhaka, Gazipur, Sylhet">
              </div>
              @error('city')<p class="co-err">{{ $message }}</p>@enderror
            </div>

            <div class="co-zone" id="co-zone" data-zone="">
              <svg class="ico"><use href="#i-truck"/></svg>
              <div>
                <strong id="co-zone-title">Delivery charge</strong>
                <span id="co-zone-text">Select your district to see the charge</span>
              </div>
            </div>

            <div class="co-field co-wide @error('address') has-err @enderror">
              <label for="f-address">Delivery address <b>*</b></label>
              <div class="co-input co-area"><svg class="ico"><use href="#i-pin"/></svg>
                <textarea id="f-address" name="address" rows="3" required maxlength="500" autocomplete="street-address" placeholder="House / road / village, area, landmark">{{ old('address') }}</textarea>
              </div>
              @error('address')<p class="co-err">{{ $message }}</p>@enderror
            </div>

            <div class="co-field co-wide @error('note') has-err @enderror">
              <label for="f-note">Anything else we should know? <em>(optional)</em></label>
              <div class="co-input co-area"><svg class="ico"><use href="#i-note"/></svg>
                <textarea id="f-note" name="note" rows="2" maxlength="500" placeholder="Delivery time, special instructions…">{{ old('note') }}</textarea>
              </div>
              @error('note')<p class="co-err">{{ $message }}</p>@enderror
            </div>
          </div>
        </div>
      </div>

      {{-- Right column: items table → coupon → policy → place order --}}
      <aside class="co-side">
        <div class="co-sec-head">
          <h2>Your items</h2>
          <span class="co-pill" id="co-count">{{ $itemCount }} {{ $itemCount === 1 ? 'item' : 'items' }}</span>
        </div>

        <div class="co-tablecard">
          <table class="co-table">
            <thead>
              <tr><th>Product</th><th class="c-qty">Quantity</th><th colspan="2">Price</th></tr>
            </thead>

            <tbody id="co-items">
              @foreach ($lines as $line)
                @php $p = $line['product']; @endphp
                <tr class="co-item" data-id="{{ $p->id }}" data-price="{{ (int) $p->price }}" data-stock="{{ (int) $p->stock_quantity }}">
                  <td class="co-name"><a class="co-title" href="{{ $p->url() }}" title="{{ $p->title }}">{{ $p->title }}</a></td>
                  <td class="c-qty">
                    <div class="co-stepper" role="group" aria-label="Quantity">
                      <button type="button" data-act="minus" aria-label="Decrease quantity" @if ($line['qty'] <= 1) disabled @endif><svg class="ico"><use href="#i-minus"/></svg></button>
                      <output class="co-qty" aria-live="polite">{{ $line['qty'] }}</output>
                      <button type="button" data-act="plus" aria-label="Increase quantity" @if ($line['qty'] >= (int) $p->stock_quantity) disabled @endif><svg class="ico"><use href="#i-plus"/></svg></button>
                    </div>
                  </td>
                  <td class="co-line">৳ {{ number_format($line['total']) }}</td>
                  <td class="co-delcell"><button type="button" class="co-del" data-act="remove" aria-label="Remove {{ $p->title }}" title="Remove"><svg class="ico"><use href="#i-trash"/></svg></button></td>
                </tr>
              @endforeach
            </tbody>

            <tfoot>
              <tr><th colspan="2" scope="row">Subtotal</th><td colspan="2"><span id="t-subtotal">৳ {{ number_format($subtotal) }}</span></td></tr>
              <tr><th colspan="2" scope="row">Delivery charge</th><td colspan="2"><span id="t-delivery" class="muted">Select district</span></td></tr>
              <tr id="t-discount-row" @if ($discount > 0) class="has-disc" @endif><th colspan="2" scope="row">Discount</th><td colspan="2"><span id="t-discount">{{ $discount > 0 ? '− ৳ ' . number_format($discount) : '৳ 0' }}</span></td></tr>
              <tr class="co-grand"><th colspan="2" scope="row">Total</th><td colspan="2"><span id="t-total" class="co-grand-val">৳ {{ number_format($subtotal - $discount) }}</span></td></tr>
            </tfoot>
          </table>
        </div>

        {{-- Coupon --}}
        <div class="co-coupon" id="co-coupon">
          <div class="co-coupon-form" id="co-coupon-form" @if ($coupon) hidden @endif>
            <label for="f-coupon"><svg class="ico"><use href="#i-tag"/></svg> Have a coupon code?</label>
            <div class="co-coupon-row">
              <div class="co-input">
                <input id="f-coupon" type="text" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="30" placeholder="e.g. SAVE100">
              </div>
              <button type="button" class="co-btn-apply" id="co-coupon-apply">Apply Coupon</button>
            </div>
          </div>
          <div class="co-coupon-on" id="co-coupon-on" @if (!$coupon) hidden @endif>
            <svg class="ico"><use href="#i-tag"/></svg>
            <div>
              <strong id="co-coupon-code">{{ $coupon ? $coupon->code : '' }}</strong>
              <span id="co-coupon-label">{{ $coupon ? $coupon->label() . ' applied' : '' }}</span>
            </div>
            <button type="button" id="co-coupon-remove" aria-label="Remove coupon"><svg class="ico"><use href="#i-x"/></svg></button>
          </div>
          <p class="co-coupon-msg" id="co-coupon-msg" role="status" hidden></p>
        </div>

        {{-- Payment method --}}
        <div class="co-pay @error('payment_method') has-err @enderror @error('bkash_amount') has-err @enderror @error('bkash_trx_id') has-err @enderror">
          <div class="co-pay-title">Payment method</div>
          <div class="co-pay-opts">
            <label class="co-pay-opt {{ $payOld === 'cod' ? 'is-on' : '' }}">
              <input type="radio" name="payment_method" value="cod" {{ $payOld === 'cod' ? 'checked' : '' }}>
              <span class="mark"></span><span>Cash on Delivery</span>
            </label>
            <label class="co-pay-opt bk {{ $payOld === 'bkash' ? 'is-on' : '' }}">
              <input type="radio" name="payment_method" value="bkash" {{ $payOld === 'bkash' ? 'checked' : '' }}>
              <span class="mark"></span><span>bKash</span>
            </label>
          </div>
          <p class="co-pay-sum" id="bk-sum" hidden>
            <span id="bk-sum-text"></span>
            <button type="button" id="bk-edit">Edit</button>
          </p>
          @error('payment_method')<p class="co-err">{{ $message }}</p>@enderror
          @error('bkash_amount')<p class="co-err">{{ $message }}</p>@enderror
          @error('bkash_trx_id')<p class="co-err">{{ $message }}</p>@enderror
        </div>

        {{-- Terms (ticked by default) --}}
        <div class="co-terms @error('terms') has-err @enderror">
          <label class="co-check">
            <input type="checkbox" name="terms" value="1" id="f-terms" required {{ $termsChecked ? 'checked' : '' }}>
            <span class="box"><svg class="ico"><use href="#i-check"/></svg></span>
            <span class="txt">I agree to the
              <a href="https://ayub365.com/terms.php" target="_blank" rel="noopener">Terms &amp; Conditions</a>,
              <a href="https://ayub365.com/privacy.php" target="_blank" rel="noopener">Privacy Policy</a>,
              <a href="https://ayub365.com/return-policy.php" target="_blank" rel="noopener">Return Policy</a> and
              <a href="https://ayub365.com/delivery-policy.php" target="_blank" rel="noopener">Delivery Policy</a>.
            </span>
          </label>
          @error('terms')<p class="co-err">{{ $message }}</p>@enderror
        </div>

        <button type="submit" class="co-submit" id="co-submit">
          <svg class="ico"><use href="#i-lock"/></svg>
          <span>Place order</span>
          <b id="t-btn-total">৳ {{ number_format($subtotal - $discount) }}</b>
        </button>
      </aside>

      {{-- bKash window (small pop-up) --}}
      <div class="bk-modal" id="bk-modal" role="dialog" aria-modal="true" aria-labelledby="bk-title" hidden>
        <div class="bk-box">
          <div class="bk-head">
            <strong id="bk-title">bKash Payment</strong>
            <button type="button" class="bk-x" id="bk-close" aria-label="Close">&times;</button>
          </div>
          <div class="bk-body">
            <div class="bk-top">
              <div class="bk-info">
                <span class="bk-pill">Send Money</span>
                <div class="bk-numrow">
                  <b id="bk-number">{{ $bkash['number'] }}</b>
                  <button type="button" class="bk-copy" id="bk-copy">Copy</button>
                </div>
                <p class="bk-steps">bKash app &rarr; Send Money &rarr; this number (or scan the QR) &rarr; send the amount.</p>
              </div>
              <div class="bk-qr">
                @if ($bkash['qr'])
                  <img src="{{ $bkash['qr'] }}" alt="bKash QR code">
                @else
                  <span>QR code</span>
                @endif
              </div>
            </div>

            <div class="bk-send"><span>Total to send</span><b id="bk-total">৳ {{ number_format($subtotal - $discount) }}</b></div>
            <p class="bk-hint" id="bk-hint">Select your district first, so the delivery charge is included.</p>

            <div class="bk-fields">
              <div class="bk-field">
                <label for="bk-amount">Amount sent (৳) <b>*</b></label>
                <input id="bk-amount" type="text" inputmode="numeric" name="bkash_amount" value="{{ old('bkash_amount') }}" autocomplete="off" placeholder="e.g. 4080">
              </div>
              <div class="bk-field">
                <label for="bk-trx">Transaction ID <em>(optional)</em></label>
                <input id="bk-trx" type="text" name="bkash_trx_id" value="{{ old('bkash_trx_id') }}" maxlength="20" autocomplete="off" autocapitalize="characters" placeholder="e.g. 9A1B2C3D4E">
              </div>
            </div>

            <p class="bk-msg" id="bk-msg" role="alert" hidden></p>
            <button type="button" class="bk-confirm" id="bk-confirm">Confirm</button>
            <p class="bk-note">We will verify your payment shortly. You can confirm with just the amount.</p>
          </div>
        </div>
      </div>
    </form>
  </section>

  <script type="application/json" id="co-data">{!! json_encode($jsData, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endsection

@push('scripts')
  <script src="{{ asset('assets/js/checkout.js') }}"></script>
@endpush
