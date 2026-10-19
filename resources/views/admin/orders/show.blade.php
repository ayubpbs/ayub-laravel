@extends('admin.layout')

@section('title', 'Order #' . $order->id)

@section('content')
  @php $ax = \App\Support\AdminAccess::class; @endphp
  <p class="ad-crumbs"><a href="{{ route('admin.orders.index') }}">← All orders</a></p>
  @php
    $claimsReady = \App\Models\ServiceRequest::tableReady();
    $orderClaims = $claimsReady && $ax::can('claims_view') ? $order->claims()->latest('id')->get() : collect();
  @endphp
  <h1 class="ad-title">Order #{{ $order->id }} <span class="ad-st st-{{ $order->status }}">{{ $order->statusLabel() }}</span>@if ($order->isClaimOrder()) <span class="ad-st st-courier_sent">{{ $order->typeLabel() }}</span>@endif</h1>
  <p>
    @if ($ax::can('orders_create'))
      <a class="ad-btn ad-btn-gray" href="{{ route('admin.orders.create', ['reorder' => $order->id]) }}">↻ Reorder</a>
    @endif
    @if ($claimsReady && $order->status === 'delivered' && $ax::can('claims_manage'))
      <a class="ad-btn ad-btn-gray" href="{{ route('admin.claims.create', ['order' => $order->id]) }}">↩ Open claim</a>
      <a class="ad-btn ad-btn-gray" href="{{ route('admin.claims.warranty.create', ['order' => $order->id]) }}">🛡 Warranty claim</a>
    @endif
  </p>

  <div class="ad-two">
    <section class="ad-card">
      <h2>Customer details</h2>
      <dl class="ad-dl">
        <dt>Name</dt><dd>{{ $order->name }}</dd>
        <dt>Mobile</dt><dd><a href="tel:{{ $order->phone }}">{{ $order->phone }}</a>@if ($ax::can('customers')) · <a class="ad-link" href="{{ route('admin.customers.show', $order->phone) }}">Customer history</a>@endif</dd>
        <dt>Address</dt><dd>{{ $order->address }}</dd>
        @if ($order->district)
          <dt>District</dt><dd>{{ $order->district }}@if ($order->upazila) · {{ $order->upazila }}@endif @if ($order->city) · {{ $order->city }}@endif</dd>
        @endif
        @if ($order->note)
          <dt>Note</dt><dd>{{ $order->note }}</dd>
        @endif
        <dt>Order time</dt><dd>{{ $order->created_at->format('d M Y, h:i A') }}</dd>
        <dt>Payment</dt><dd>{{ $order->paymentMethodLabel() }}</dd>
        @if ($order->created_by_name)
          <dt>Created by</dt><dd>{{ $order->created_by_name }} <span class="ad-muted">(admin panel)</span></dd>
        @endif
        @if ($order->source_order_id)
          <dt>{{ $order->isClaimOrder() ? $order->typeLabel() . ' for' : 'Reorder of' }}</dt><dd><a class="ad-link" href="{{ route('admin.orders.show', $order->source_order_id) }}">Order #{{ $order->source_order_id }}</a>@if ($order->service_request_id && $ax::can('claims_view')) · <a class="ad-link" href="{{ route('admin.claims.show', $order->service_request_id) }}">Claim #{{ $order->service_request_id }}</a>@endif</dd>
        @endif
        @if ($order->delivered_at)
          <dt>Delivered</dt><dd>{{ $order->delivered_at->format('d M Y, h:i A') }}</dd>
        @endif
      </dl>
    </section>

    <section class="ad-card">
      <h2>Change status</h2>
      @if ($order->isLocked())
        <p class="ad-muted">This order is {{ strtolower($order->statusLabel()) }}, so its status can no longer be changed.</p>
      @elseif (!$ax::can('orders_manage'))
        <p class="ad-muted">You do not have permission to change the order status.</p>
      @else
        <form action="{{ route('admin.orders.status', $order->id) }}" method="POST" class="ad-form">
          @csrf
          <select name="status">
            @foreach (\App\Models\Order::statuses() as $key => $label)
              <option value="{{ $key }}" {{ $order->status === $key ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
          </select>
          <button type="submit" class="ad-btn">Save</button>
        </form>
        <p class="ad-hint">Choosing "Cancelled" or "Returned" puts the products back in stock, and the status cannot be changed afterwards.</p>
      @endif
    </section>
  </div>

  @if ($order->isBkash())
    @php $bkOk = $order->bkashVerified(); @endphp
    <section class="ad-card">
      <div class="ad-card-head">
        <h2>bKash payment <span class="ad-st {{ $bkOk ? 'st-delivered' : 'st-pending' }}">{{ $bkOk ? 'Verified' : 'Waiting for verification' }}</span></h2>
      </div>

      <dl class="ad-dl">
        <dt>Customer says sent</dt><dd>৳ {{ number_format((int) $order->bkash_amount) }}</dd>
        <dt>Transaction ID</dt><dd>{{ $order->bkash_trx_id ?: 'Not given' }}</dd>
        <dt>Order total</dt><dd>৳ {{ number_format($order->total) }}</dd>
      </dl>

      @error('amount')<p class="ad-red" style="margin:10px 0 0">{{ $message }}</p>@enderror

      @if ($bkOk)
        <p class="ad-muted" style="margin-top:10px">Verified. The payment is listed below and shows on the invoice.</p>
      @elseif (!$order->isLocked() && $ax::can('payments'))
        <form action="{{ route('admin.bkash.verify', $order->id) }}" method="POST" class="ad-form" style="margin-top:12px" onsubmit="return confirm('Add this bKash payment to the order and invoice?');">
          @csrf
          <label for="bk-amount">Amount received (৳)</label>
          <input id="bk-amount" type="number" name="amount" value="{{ old('amount', $order->bkash_amount) }}" min="1" step="1" required>
          <button type="submit" class="ad-btn">✔ Verify &amp; add to invoice</button>
        </form>
        <p class="ad-hint">Check the money in your bKash app first. You can correct the amount if it is different. Verifying records it as a payment, so the invoice shows it as Paid. Deleting that payment below puts this back to "Waiting for verification".</p>
      @endif
    </section>
  @endif

  @php
    $net  = $order->netPaid();
    $due  = $order->dueAmount();
    $inv  = $order->invoice;
  @endphp
  <section class="ad-card">
    <div class="ad-card-head">
      <h2>Payment <span class="ad-st {{ \App\Models\Order::paymentBadge($order->paymentStatus()) }}">{{ $order->paymentLabel() }}</span></h2>
      @if ($inv && $ax::can('invoices'))
        <a href="{{ route('admin.invoices.show', $inv->id) }}" target="_blank">🖨 Invoice {{ $inv->number() }}</a>
      @endif
    </div>

    <dl class="ad-dl">
      <dt>Order total</dt><dd>৳ {{ number_format($order->total) }}</dd>
      <dt>Received</dt><dd>৳ {{ number_format($net) }}</dd>
      @if ($order->claimRefunded() > 0)
        <dt>Refunded for claims</dt><dd class="ad-red">− ৳ {{ number_format($order->claimRefunded()) }} <small class="ad-muted">(the total and invoice stay as they were)</small></dd>
      @endif
      <dt>{{ $order->isLocked() ? 'To refund' : 'Due' }}</dt>
      <dd><strong class="{{ ($order->isLocked() ? $net : $due) > 0 ? 'ad-red' : '' }}">৳ {{ number_format($order->isLocked() ? max(0, $net) : $due) }}</strong></dd>
    </dl>

    @if ($order->payments->isNotEmpty())
      <div class="ad-scroll" style="margin-top:12px">
        <table class="ad-table">
          <tr><th>Date</th><th>Method</th><th>Transaction</th><th class="r">Amount</th><th></th></tr>
          @foreach ($order->payments->sortBy('paid_at') as $p)
            <tr>
              <td>{{ $p->paid_at->format('d M Y, h:i A') }}</td>
              <td>{{ $p->methodLabel() }} @if ($p->isRefund())<span class="ad-st st-returned">Refund</span>@endif</td>
              <td>{{ $p->trx_id ?: '—' }}@if ($p->note)<br><small class="ad-muted">{{ $p->note }}</small>@endif</td>
              <td class="r {{ $p->isRefund() ? 'ad-red' : '' }}">{{ $p->isRefund() ? '−' : '' }}৳ {{ number_format($p->amount) }}</td>
              <td>
                @if ($ax::can('payments'))
                <form action="{{ route('admin.payments.destroy', $p->id) }}" method="POST" onsubmit="return confirm('Delete this payment record?');">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="ad-link ad-del">Delete</button>
                </form>
                @endif
              </td>
            </tr>
          @endforeach
        </table>
      </div>
    @endif

    <div class="ad-submit" style="margin-top:12px">
      @if ($ax::can('payments'))
      @if (!$order->isLocked() && $due > 0)
        <form action="{{ route('admin.payments.collect', $order->id) }}" method="POST" onsubmit="return confirm('Record ৳ {{ number_format($due) }} as cash on delivery collected?');">
          @csrf
          <button type="submit" class="ad-btn">✔ Mark fully paid (cash on delivery)</button>
        </form>
      @endif
      @if (!$order->isLocked() || $net > 0)
        <a class="ad-btn ad-btn-gray" href="{{ route('admin.payments.create', ['order' => $order->id]) }}">Record payment / refund</a>
      @endif
      @endif
      @if (!$inv && !$order->isLocked() && $ax::can('invoices'))
        <form action="{{ route('admin.invoices.generate', $order->id) }}" method="POST">
          @csrf
          <button type="submit" class="ad-btn ad-btn-gray">Create invoice</button>
        </form>
      @endif
    </div>
  </section>

  @if ($orderClaims->isNotEmpty())
    <section class="ad-card">
      <h2>Claims on this order</h2>
      <div class="ad-scroll">
        <table class="ad-table">
          <tr><th>Claim</th><th>Type</th><th>Solution</th><th>Status</th><th>Opened</th></tr>
          @foreach ($orderClaims as $c)
            <tr>
              <td><a class="ad-link" href="{{ route('admin.claims.show', $c->id) }}"><strong>#{{ $c->id }}</strong></a></td>
              <td>{{ $c->typeLabel() }}</td>
              <td>{{ $c->resolution ? ucfirst($c->resolution) : '—' }}@if ($c->replacement_order_id) · <a class="ad-link" href="{{ route('admin.orders.show', $c->replacement_order_id) }}">order #{{ $c->replacement_order_id }}</a>@endif</td>
              <td><span class="ad-st {{ \App\Models\ServiceRequest::badge($c->status) }}">{{ $c->statusLabel() }}</span></td>
              <td>{{ $c->created_at->format('d M Y') }}</td>
            </tr>
          @endforeach
        </table>
      </div>
    </section>
  @endif

  <section class="ad-card">
    <h2>Courier</h2>
    @if ($order->consignment_id)
      <dl class="ad-dl">
        <dt>Courier</dt><dd>Steadfast</dd>
        <dt>Consignment</dt><dd>{{ $order->consignment_id }}</dd>
        <dt>Tracking code</dt><dd>{{ $order->tracking_code ?: '—' }}</dd>
        <dt>Courier status</dt><dd><span class="ad-st {{ \App\Support\Steadfast::badge($order->courier_status) }}">{{ \App\Support\Steadfast::label($order->courier_status) }}</span></dd>
        <dt>Sent</dt><dd>{{ optional($order->courier_sent_at)->format('d M Y, h:i A') }}</dd>
        <dt>Sent by</dt><dd>{{ $order->courier_sent_by_name ?: '—' }}</dd>
      </dl>
      @if ($ax::can('courier'))
        <form action="{{ route('admin.courier.refresh', $order->id) }}" method="POST" style="margin-top:12px">
          @csrf
          <button type="submit" class="ad-btn ad-btn-gray">⟳ Refresh courier status</button>
        </form>
      @endif
    @elseif ($order->status === 'confirmed')
      <p class="ad-muted">Not sent to a courier yet.</p>
      @if ($ax::can('courier'))
        <form action="{{ route('admin.courier.send', $order->id) }}" method="POST" onsubmit="return confirm('Send order #{{ $order->id }} to Steadfast now?');">
          @csrf
          <button type="submit" class="ad-btn">🚚 Send to Steadfast</button>
        </form>
      @endif
    @else
      <p class="ad-muted">An order can be sent to Steadfast once it is "Confirmed".</p>
    @endif
  </section>

  <section class="ad-card">
    <h2>Items</h2>
    <div class="ad-scroll">
      <table class="ad-table">
        <tr><th>Product</th><th>Price</th><th>Quantity</th><th class="r">Total</th></tr>
        @foreach ($order->items as $item)
          <tr>
            <td>{{ $item->title }}
              @if ($item->warranty_source && $item->warranty_type && $item->warranty_type !== 'none' && $item->warranty_days)
                <br><small class="ad-muted">🛡 {{ \App\Support\WarrantyPolicy::typeLabel($item->warranty_type) }}, {{ $item->warranty_days }} days@if ($order->delivered_at) (until {{ $order->delivered_at->copy()->addDays((int) $item->warranty_days)->format('d M Y') }})@endif</small>
              @endif
            </td>
            <td>৳ {{ number_format($item->price) }}</td>
            <td>{{ $item->quantity }}</td>
            <td class="r">৳ {{ number_format($item->line_total) }}</td>
          </tr>
        @endforeach
        <tr><td colspan="3" class="r">Subtotal</td><td class="r">৳ {{ number_format($order->subtotal) }}</td></tr>
        @if ($order->discount > 0)
          <tr><td colspan="3" class="r">Discount @if ($order->coupon_code)<span class="ad-st st-confirmed">{{ $order->coupon_code }}</span>@endif</td><td class="r ad-green">− ৳ {{ number_format($order->discount) }}</td></tr>
        @endif
        <tr><td colspan="3" class="r">Delivery charge</td><td class="r">৳ {{ number_format($order->delivery_charge) }}</td></tr>
        <tr class="ad-total"><td colspan="3" class="r">Total</td><td class="r">৳ {{ number_format($order->total) }}</td></tr>
      </table>
    </div>
  </section>
@endsection
