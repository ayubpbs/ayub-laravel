@extends('admin.layout')

@section('title', 'New Warranty Claim')

@section('content')
  @php
    $chip = ['in_warranty' => 'st-delivered', 'expired' => 'st-cancelled', 'not_covered' => 'st-cancelled', 'unknown' => 'st-pending'];
    $chipText = ['in_warranty' => 'In warranty', 'expired' => 'Expired', 'not_covered' => 'No warranty', 'unknown' => 'Date needed'];
    $src = ['product' => 'product policy', 'shop' => 'shop default', 'backfilled' => "today's product settings (old order)", 'continued' => 'continues the original warranty'];
  @endphp

  <p class="ad-crumbs"><a href="{{ route('admin.claims.index', ['kind' => 'warranty']) }}">← Warranty claims</a></p>
  <h1 class="ad-title">New warranty claim</h1>

  @if (!$order)
    <form class="ad-card ad-form" method="GET" action="{{ route('admin.claims.warranty.create') }}">
      <label>Order number
        <input type="text" name="order" placeholder="e.g. 125" required autofocus>
      </label>
      <div class="ad-submit"><button type="submit" class="ad-btn">Find order</button></div>
      <p class="ad-hint">A warranty claim is made for a <strong>delivered</strong> order. The warranty that applied when the goods were sold is checked for you. The original order and its invoice are never changed.</p>
    </form>
  @else
    <section class="ad-card">
      <div class="ad-card-head">
        <h2>Order #{{ $order->id }} — {{ $order->name }} <span class="ad-st st-{{ $order->status }}">{{ $order->statusLabel() }}</span></h2>
        <a href="{{ route('admin.orders.show', $order->id) }}">View order →</a>
      </div>
      <dl class="ad-dl">
        <dt>Mobile</dt><dd>{{ $order->phone }}</dd>
        <dt>Delivered</dt><dd>{{ $order->delivered_at ? $order->delivered_at->format('d M Y') . ' (' . $order->delivered_at->diffForHumans() . ')' : 'Date not recorded on this order' }}</dd>
      </dl>
    </section>

    @if ($order->status !== 'delivered')
      <div class="ad-flash ad-warn">This order is {{ strtolower($order->statusLabel()) }}. A warranty claim can only be opened for a delivered order.</div>
      <p><a class="ad-link" href="{{ route('admin.claims.warranty.create') }}">Choose another order</a></p>
    @else
      <form class="ad-form" method="POST" action="{{ route('admin.claims.warranty.store') }}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="order_id" value="{{ $order->id }}">

        <section class="ad-card">
          <h2>1. Which product has the problem?</h2>
          <div class="ad-scroll">
            <table class="ad-table">
              <tr><th></th><th>Product</th><th>Ordered</th><th>Can claim</th><th>Warranty of this purchase</th><th>Check</th></tr>
              @foreach ($rows as $r)
                @php $a = $r['a']; $p = $a['policy']; @endphp
                <tr>
                  <td><input type="radio" name="order_item_id" value="{{ $r['line']->id }}" {{ (string) old('order_item_id') === (string) $r['line']->id ? 'checked' : '' }} {{ $r['left'] <= 0 ? 'disabled' : '' }} required></td>
                  <td>{{ $r['line']->title }}<br><small class="ad-muted">Paid ৳ {{ number_format($r['line']->price) }} each</small></td>
                  <td>{{ $r['line']->quantity }}</td>
                  <td>{{ $r['left'] }}</td>
                  <td>
                    @if ($p['type'] === 'none')
                      No warranty <small class="ad-muted">({{ $src[$p['source']] ?? $p['source'] }})</small>
                    @else
                      {{ \App\Support\WarrantyPolicy::typeLabel($p['type']) }} · {{ $p['days'] }} days<br>
                      <small class="ad-muted">{{ $src[$p['source']] ?? $p['source'] }}</small>
                      @if ($p['terms'])<br><small class="ad-muted">{!! nl2br(e($p['terms'])) !!}</small>@endif
                    @endif
                  </td>
                  <td>
                    <span class="ad-st {{ $chip[$a['state']] ?? 'st-pending' }}">{{ $chipText[$a['state']] ?? $a['state'] }}</span>
                    <br><small class="ad-muted">{{ $a['message'] }}</small>
                  </td>
                </tr>
              @endforeach
            </table>
          </div>
        </section>

        <section class="ad-card">
          <h2>2. The problem</h2>
          <div class="ad-row">
            <label>Pieces in this claim *
              <input type="number" name="quantity" min="1" max="999" value="{{ old('quantity', 1) }}" required style="width:110px">
            </label>
            <label>Serial number <small>(optional)</small>
              <input type="text" name="serial_number" maxlength="100" value="{{ old('serial_number') }}">
            </label>
            @unless ($order->delivered_at)
              <label>Date the customer received it *
                <input type="date" name="start_date" value="{{ old('start_date') }}" max="{{ now()->toDateString() }}" required>
                <small>This order has no recorded delivery date. The warranty counts from this date.</small>
              </label>
            @endunless
          </div>
          <label>Why is the customer claiming the warranty? *
            <textarea name="reason" rows="2" maxlength="1000" required placeholder="e.g. Does not switch on">{{ old('reason') }}</textarea>
          </label>
          <label>Symptoms <small>(optional: what exactly happens, since when)</small>
            <textarea name="symptoms" rows="3" maxlength="2000">{{ old('symptoms') }}</textarea>
          </label>
        </section>

        <section class="ad-card">
          <h2>3. Photos / videos <small>(private, optional)</small></h2>
          <input type="file" name="files[]" multiple accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/webm">
          <label>Note about the files <small>(optional)</small>
            <input type="text" name="evidence_note" maxlength="250" value="{{ old('evidence_note') }}">
          </label>
          <p class="ad-hint">Up to {{ \App\Services\ClaimService::MAX_FILES }} files. Photos (JPG, PNG, WebP) up to 5 MB, videos (MP4, MOV, WebM) up to 30 MB. Kept in a private folder.</p>
        </section>

        <label class="ad-check">
          <input type="checkbox" name="record_ineligible" value="1" {{ old('record_ineligible') ? 'checked' : '' }}>
          If the warranty has ended (or there is none): save the request anyway, as <strong>rejected</strong>, so there is a record of the call
        </label>

        <div class="ad-submit"><button type="submit" class="ad-btn">Open warranty claim</button></div>
      </form>
    @endif
  @endif
@endsection
