@extends('admin.layout')

@section('title', 'New Claim')

@section('content')
  <p class="ad-crumbs"><a href="{{ route('admin.claims.index') }}">← All claims</a></p>
  <h1 class="ad-title">New claim</h1>

  @if (!$order)
    <form class="ad-card ad-form" method="GET" action="{{ route('admin.claims.create') }}">
      <label>Order number
        <input type="text" name="order" placeholder="e.g. 125" required autofocus>
      </label>
      <div class="ad-submit"><button type="submit" class="ad-btn">Find order</button></div>
      <p class="ad-hint">A claim can only be opened for a <strong>delivered</strong> order. The original order and its invoice are never changed.</p>
    </form>
  @else
    <section class="ad-card">
      <div class="ad-card-head">
        <h2>Order #{{ $order->id }} — {{ $order->name }} <span class="ad-st st-{{ $order->status }}">{{ $order->statusLabel() }}</span></h2>
        <a href="{{ route('admin.orders.show', $order->id) }}">View order →</a>
      </div>
      <dl class="ad-dl">
        <dt>Mobile</dt><dd>{{ $order->phone }}</dd>
        <dt>Delivered</dt><dd>{{ $order->delivered_at ? $order->delivered_at->format('d M Y') . ' (' . $order->delivered_at->diffForHumans() . ')' : 'Date not recorded' }}</dd>
        <dt>Order total</dt><dd>৳ {{ number_format($order->total) }}</dd>
      </dl>
    </section>

    @if ($order->status !== 'delivered')
      <div class="ad-flash ad-warn">This order is {{ strtolower($order->statusLabel()) }}. A claim can only be opened for a delivered order.</div>
      <p><a class="ad-link" href="{{ route('admin.claims.create') }}">Choose another order</a></p>
    @elseif (array_sum($left) <= 0)
      <div class="ad-flash ad-warn">Every piece of this order is already in another claim.</div>
      <p><a class="ad-link" href="{{ route('admin.claims.create') }}">Choose another order</a></p>
    @else
      <form class="ad-form" method="POST" action="{{ route('admin.claims.store') }}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="order_id" value="{{ $order->id }}">

        <section class="ad-card">
          <h2>1. What is wrong?</h2>
          <div class="ad-row">
            <label>Type of claim *
              <select name="type" id="c-type" required>
                <option value="">Choose…</option>
                @foreach (\App\Models\ServiceRequest::types() as $k => $label)
                  <option value="{{ $k }}" {{ old('type') === $k ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
              </select>
            </label>
          </div>
          <label>What the customer says
            <textarea name="reason" rows="3" maxlength="2000" placeholder="Describe the problem in the customer's words">{{ old('reason') }}</textarea>
          </label>
          <label class="ad-check">
            <input type="checkbox" name="return_required" value="1" {{ old('return_required', '1') ? 'checked' : '' }}>
            The goods must come back to us (we inspect them before anything goes on the shelf)
          </label>
        </section>

        <section class="ad-card">
          <h2>2. Which products?</h2>
          <p class="ad-hint">Enter how many pieces are part of the claim. Leave 0 for products that are fine.</p>
          <div class="ad-scroll">
            <table class="ad-table">
              <tr><th>Product</th><th>Price paid</th><th>Ordered</th><th>Can claim</th><th>Claim qty</th><th class="c-recv" hidden>Product received (ID)</th></tr>
              @foreach ($order->items as $it)
                <tr>
                  <td>{{ $it->title }}</td>
                  <td>৳ {{ number_format($it->price) }}</td>
                  <td>{{ $it->quantity }}</td>
                  <td>{{ $left[$it->id] ?? 0 }}</td>
                  <td><input type="number" name="qty[{{ $it->id }}]" min="0" max="{{ $left[$it->id] ?? 0 }}" value="{{ old('qty.' . $it->id, 0) }}" style="width:90px" {{ ($left[$it->id] ?? 0) <= 0 ? 'disabled' : '' }}></td>
                  <td class="c-recv" hidden><input type="number" name="received[{{ $it->id }}]" min="1" value="{{ old('received.' . $it->id) }}" placeholder="same product" style="width:130px"></td>
                </tr>
              @endforeach
            </table>
          </div>
          <p class="ad-hint c-recv" hidden>Wrong product only: if the customer received a different product that is in your shop, enter its product ID so it is counted correctly if it goes back to stock. Leave empty if it is the same product.</p>
        </section>

        <section class="ad-card">
          <h2>3. Photos / videos <small>(private, optional)</small></h2>
          <input type="file" name="files[]" multiple accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/webm">
          <label>Note about the files <small>(optional)</small>
            <input type="text" name="evidence_note" maxlength="250" value="{{ old('evidence_note') }}">
          </label>
          <p class="ad-hint">Up to {{ \App\Services\ClaimService::MAX_FILES }} files. Photos (JPG, PNG, WebP) up to 5 MB, videos (MP4, MOV, WebM) up to 30 MB. They are kept in a private folder and only people with the claims permission can open them.</p>
        </section>

        <div class="ad-submit"><button type="submit" class="ad-btn">Open claim</button></div>
      </form>

      <script>
        (function () {
          var t = document.getElementById('c-type');
          function sync() { document.querySelectorAll('.c-recv').forEach(function (e) { e.hidden = t.value !== 'wrong_product'; }); }
          t.addEventListener('change', sync); sync();
        })();
      </script>
    @endif
  @endif
@endsection
