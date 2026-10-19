@extends('admin.layout')

@section('title', 'Claim #' . $claim->id)

@section('content')
  @php
    $ax        = \App\Support\AdminAccess::class;
    $canManage = $ax::can('claims_manage');
    $approved  = $claim->status === 'approved';
    $new       = $claim->replacementOrder;
    $isOrderRes = in_array($claim->resolution, ['replacement', 'exchange'], true);
    $isWarranty = $claim->type === 'warranty';
  @endphp

  <p class="ad-crumbs"><a href="{{ route('admin.claims.index') }}">← All claims</a></p>
  <h1 class="ad-title">Claim #{{ $claim->id }} <span class="ad-st {{ \App\Models\ServiceRequest::badge($claim->status) }}">{{ $claim->statusLabel() }}</span></h1>

  <div class="ad-two">
    <section class="ad-card">
      <h2>Details</h2>
      <dl class="ad-dl">
        <dt>Customer</dt><dd>{{ $claim->customer_name }} · <a href="tel:{{ $claim->customer_phone }}">{{ $claim->customer_phone }}</a></dd>
        <dt>Original order</dt><dd><a class="ad-link" href="{{ route('admin.orders.show', $claim->order_id) }}">Order #{{ $claim->order_id }}</a> <span class="ad-muted">(not changed by this claim)</span></dd>
        <dt>Type</dt><dd>{{ $claim->typeLabel() }}</dd>
        <dt>Reason</dt><dd>{{ $claim->reason ?: '—' }}</dd>
        <dt>Goods come back</dt><dd>{{ $claim->return_required ? 'Yes, they must be inspected' : 'No' }}</dd>
        <dt>Opened</dt><dd>{{ $claim->created_at->format('d M Y, h:i A') }} by {{ $claim->opened_by_name ?: '—' }}</dd>
        @if ($claim->decided_at)
          <dt>Decision</dt><dd>{{ $claim->statusLabel() }} by {{ $claim->decided_by_name }} on {{ $claim->decided_at->format('d M Y, h:i A') }}</dd>
          <dt>Solution</dt><dd>{{ $claim->resolutionLabel() }}</dd>
          @if ($claim->decision_note)<dt>Note</dt><dd>{{ $claim->decision_note }}</dd>@endif
        @endif
        @if ($claim->closed_at)
          <dt>Resolved</dt><dd>{{ $claim->closed_at->format('d M Y, h:i A') }} by {{ $claim->closed_by_name }}</dd>
        @endif
      </dl>
    </section>

    @if ($isWarranty)
      @include('admin.claims._warranty_side')
    @else
    <section class="ad-card">
      <h2>Decision</h2>
      @if ($claim->status !== 'open')
        <p class="ad-muted">This claim is {{ strtolower($claim->statusLabel()) }}.</p>
      @elseif (!$canManage)
        <p class="ad-muted">You do not have permission to decide on claims.</p>
      @else
        <form action="{{ route('admin.claims.decide', $claim->id) }}" method="POST" class="ad-form">
          @csrf
          <label>How will it be solved? <small>(for Approve)</small>
            <select name="resolution">
              @foreach (\App\Models\ServiceRequest::resolutions() as $k => $label)
                <option value="{{ $k }}" {{ old('resolution', $claim->type === 'exchange' ? 'exchange' : 'replacement') === $k ? 'selected' : '' }}>{{ $label }}</option>
              @endforeach
            </select>
          </label>
          <label>Note <small>(needed for Reject)</small>
            <textarea name="note" rows="2" maxlength="1000">{{ old('note') }}</textarea>
          </label>
          <div class="ad-submit">
            <button type="submit" name="action" value="approve" class="ad-btn">✔ Approve</button>
            <button type="submit" name="action" value="reject" class="ad-btn ad-btn-gray" onclick="return confirm('Reject this claim?');">✖ Reject</button>
          </div>
        </form>
      @endif
    </section>
    @endif
  </div>

  @if ($isWarranty && $warranty)
    @include('admin.claims._warranty_work')
  @endif

  {{-- ===== Items and inspection ===== --}}
  <section class="ad-card">
    <h2>Claimed products</h2>
    @php $canInspect = !$isWarranty && $approved && $claim->return_required && $ax::can('claims_inspect') && !$claim->fullyInspected(); @endphp

    @if ($canInspect)
      <form action="{{ route('admin.claims.inspect', $claim->id) }}" method="POST" class="ad-form">
        @csrf
    @endif
    <div class="ad-scroll">
      <table class="ad-table">
        <tr>
          <th>Product</th><th>Price paid</th><th>Qty</th>
          @if (!$isWarranty && $claim->return_required && in_array($claim->status, ['approved', 'resolved'], true))
            <th>Back to stock</th><th>To supplier</th><th>Scrap</th><th>Inspection</th>
          @endif
        </tr>
        @foreach ($claim->items as $it)
          <tr>
            <td>{{ $it->title }}
              @if ($it->received_product_id)<br><small class="ad-muted">Customer received product ID {{ $it->received_product_id }} instead</small>@endif
            </td>
            <td>৳ {{ number_format($it->price) }}</td>
            <td>{{ $it->quantity }}</td>
            @if (!$isWarranty && $claim->return_required && in_array($claim->status, ['approved', 'resolved'], true))
              @if ($it->isInspected())
                <td>{{ $it->qty_restock }}</td><td>{{ $it->qty_supplier }}</td><td>{{ $it->qty_scrap }}</td>
                <td><small class="ad-muted">{{ $it->inspected_by_name }}, {{ $it->inspected_at->format('d M Y') }}@if ($it->inspection_note)<br>{{ $it->inspection_note }}@endif</small></td>
              @elseif ($canInspect)
                <td><input type="number" min="0" max="{{ $it->quantity }}" name="rows[{{ $it->id }}][restock]" value="{{ old('rows.' . $it->id . '.restock') }}" style="width:70px"></td>
                <td><input type="number" min="0" max="{{ $it->quantity }}" name="rows[{{ $it->id }}][supplier]" value="{{ old('rows.' . $it->id . '.supplier') }}" style="width:70px"></td>
                <td><input type="number" min="0" max="{{ $it->quantity }}" name="rows[{{ $it->id }}][scrap]" value="{{ old('rows.' . $it->id . '.scrap') }}" style="width:70px"></td>
                <td><input type="text" maxlength="250" name="rows[{{ $it->id }}][note]" value="{{ old('rows.' . $it->id . '.note') }}" placeholder="What you found" style="min-width:160px"></td>
              @else
                <td colspan="4"><span class="ad-muted">Not inspected yet</span></td>
              @endif
            @endif
          </tr>
        @endforeach
      </table>
    </div>
    @if ($canInspect)
        <p class="ad-hint">For each product the three numbers must add up to its quantity. <strong>Only "Back to stock" adds stock</strong>; defective goods are never put back automatically. Once saved, a line cannot be changed.</p>
        <div class="ad-submit"><button type="submit" class="ad-btn" onclick="return confirm('Save the inspection? Pieces marked \'Back to stock\' are added to the stock now.');">Save inspection</button></div>
      </form>
    @elseif (!$isWarranty && $approved && $claim->return_required && !$claim->fullyInspected() && !$ax::can('claims_inspect'))
      <p class="ad-muted" style="margin-top:10px">Inspection needs the "Claims: inspect returned goods" permission.</p>
    @endif
  </section>

  {{-- ===== Replacement / exchange order ===== --}}
  @if ($isOrderRes && in_array($claim->status, ['approved', 'resolved'], true))
    <section class="ad-card">
      <h2>{{ ucfirst($claim->resolution) }} order</h2>
      @if ($new)
        <dl class="ad-dl">
          <dt>New order</dt><dd><a class="ad-link" href="{{ route('admin.orders.show', $new->id) }}"><strong>#{{ $new->id }}</strong></a> <span class="ad-st st-{{ $new->status }}">{{ $new->statusLabel() }}</span></dd>
          <dt>Total</dt><dd>৳ {{ number_format($new->total) }} @if ($claim->resolution === 'exchange')<span class="ad-muted">(goods ৳ {{ number_format($new->subtotal) }} − returned goods ৳ {{ number_format($new->discount) }} + delivery ৳ {{ number_format($new->delivery_charge) }})</span>@endif</dd>
          @if ($excess > 0)
            <dt>Refund due</dt><dd><strong class="ad-red">৳ {{ number_format(max(0, $excess - $refunded)) }}</strong> <span class="ad-muted">(the returned goods are worth ৳ {{ number_format($excess) }} more than the new goods)</span></dd>
          @endif
        </dl>
        <p class="ad-hint">Send it to the courier with the normal "Send to Steadfast" button on that order. The original order's courier booking is not touched.</p>
      @elseif (!$approved)
        <p class="ad-muted">No order was made.</p>
      @elseif (!$canManage || !$ax::can('orders_create'))
        <p class="ad-muted">Making the {{ $claim->resolution }} order needs the "Claims: manage" and "Orders: create" permissions.</p>
      @else
        @php
          $prefill = old('items');
          if (!is_array($prefill)) {
              $prefill = [];
              foreach ($claim->items as $it) {
                  if ($it->product_id) { $prefill[] = ['product_id' => $it->product_id, 'quantity' => $it->quantity]; }
              }
          }
          $ids  = collect($prefill)->pluck('product_id')->filter()->all();
          $prod = \App\Models\Product::whereIn('id', $ids)->get()->keyBy('id');
          $free = $claim->resolution === 'replacement';
        @endphp
        <form action="{{ route('admin.claims.order', $claim->id) }}" method="POST" class="ad-form" id="ord-form">
          @csrf
          <input type="hidden" name="client_token" value="{{ $token }}">
          <p class="ad-hint">
            @if ($free)
              <strong>Replacement:</strong> the goods below are sent free of charge. Stock goes down now, exactly like any order.
            @else
              <strong>Exchange:</strong> the new goods are charged at today's price, and the value of the returned goods is taken off. The difference is the total of the new order.
            @endif
          </p>
          <div class="ad-scroll">
            <table class="ad-table" id="ord-table">
              <tr><th>Product</th><th>Price</th><th>In stock</th><th>Qty</th><th></th></tr>
              @foreach ($prefill as $i => $row)
                @php $p = $prod->get((int) ($row['product_id'] ?? 0)); @endphp
                @if ($p)
                  <tr data-id="{{ $p->id }}" data-price="{{ (int) $p->price }}">
                    <td>{{ $p->title }}<input type="hidden" name="items[{{ $i }}][product_id]" value="{{ $p->id }}"></td>
                    <td>{{ $free ? 'Free' : '৳ ' . number_format($p->price) }}</td>
                    <td>{{ (int) $p->stock_quantity }}</td>
                    <td><input type="number" min="1" max="999" name="items[{{ $i }}][quantity]" value="{{ (int) ($row['quantity'] ?? 1) }}" style="width:80px" class="o-qty"></td>
                    <td><button type="button" class="ad-link ad-del o-rm">Remove</button></td>
                  </tr>
                @endif
              @endforeach
            </table>
          </div>
          <label style="margin-top:10px">Add another product
            <input type="text" id="o-search" placeholder="Type a product name or SKU (2+ letters)" autocomplete="off">
          </label>
          <div id="o-results" class="ad-hint"></div>

          <div class="ad-row" style="margin-top:12px">
            @if (!$free)
              <label>Value of returned goods taken off (৳)
                <input type="number" name="credit" id="o-credit" min="0" max="{{ $claim->itemsValue() }}" value="{{ old('credit', $claim->itemsValue()) }}">
                <small>At most ৳ {{ number_format($claim->itemsValue()) }} (what the customer paid for the claimed pieces).</small>
              </label>
            @endif
            <label>Delivery charge of the new order (৳)
              <input type="number" name="delivery" id="o-delivery" min="0" value="{{ old('delivery', 0) }}">
              <small>0 = you pay the delivery.</small>
            </label>
          </div>
          @if (!$free)<p class="ad-hint" id="o-sum"></p>@endif
          <div class="ad-submit"><button type="submit" class="ad-btn" onclick="return confirm('Make the {{ $claim->resolution }} order now? Stock goes down for the new goods.');">Make {{ $claim->resolution }} order</button></div>
        </form>

        <script>
          (function () {
            var free = {{ $free ? 'true' : 'false' }};
            var url  = @json(route('admin.claims.products'));
            var tbl  = document.getElementById('ord-table'), q = document.getElementById('o-search'), box = document.getElementById('o-results');
            var idx  = {{ count($prefill) + 1 }}, timer = null;

            function sum() {
              if (free) return;
              var s = 0;
              tbl.querySelectorAll('tr[data-id]').forEach(function (r) { s += (parseInt(r.dataset.price, 10) || 0) * (parseInt(r.querySelector('.o-qty').value, 10) || 0); });
              var c = parseInt(document.getElementById('o-credit').value, 10) || 0, d = parseInt(document.getElementById('o-delivery').value, 10) || 0;
              var off = Math.min(c, s), t = s - off + d, ex = Math.max(0, c - s);
              document.getElementById('o-sum').textContent = 'New goods ৳ ' + s + ' − returned goods ৳ ' + off + ' + delivery ৳ ' + d + ' = total ৳ ' + t + (ex > 0 ? '. The returned goods are worth ৳ ' + ex + ' more: refund that after making the order.' : '.');
            }
            function addRow(p) {
              var ex = tbl.querySelector('tr[data-id="' + p.id + '"]');
              if (ex) { var qi = ex.querySelector('.o-qty'); qi.value = (parseInt(qi.value, 10) || 0) + 1; sum(); return; }
              var tr = document.createElement('tr'); tr.dataset.id = p.id; tr.dataset.price = p.price;
              var td1 = document.createElement('td'); td1.textContent = p.title + ' ';
              var h = document.createElement('input'); h.type = 'hidden'; h.name = 'items[' + idx + '][product_id]'; h.value = p.id; td1.appendChild(h);
              var td2 = document.createElement('td'); td2.textContent = free ? 'Free' : '৳ ' + p.price;
              var td3 = document.createElement('td'); td3.textContent = p.stock;
              var td4 = document.createElement('td'); var qi2 = document.createElement('input');
              qi2.type = 'number'; qi2.min = 1; qi2.max = 999; qi2.value = 1; qi2.name = 'items[' + idx + '][quantity]'; qi2.className = 'o-qty'; qi2.style.width = '80px'; td4.appendChild(qi2);
              var td5 = document.createElement('td'); var b = document.createElement('button'); b.type = 'button'; b.className = 'ad-link ad-del o-rm'; b.textContent = 'Remove'; td5.appendChild(b);
              [td1, td2, td3, td4, td5].forEach(function (t) { tr.appendChild(t); }); tbl.appendChild(tr); idx++; sum();
            }
            tbl.addEventListener('click', function (e) { if (e.target.classList.contains('o-rm')) { e.target.closest('tr').remove(); sum(); } });
            tbl.addEventListener('input', sum);
            ['o-credit', 'o-delivery'].forEach(function (id) { var el = document.getElementById(id); if (el) el.addEventListener('input', sum); });
            q.addEventListener('input', function () {
              clearTimeout(timer);
              var v = q.value.trim();
              if (v.length < 2) { box.textContent = ''; return; }
              timer = setTimeout(function () {
                fetch(url + '?q=' + encodeURIComponent(v), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
                  .then(function (r) { return r.json(); })
                  .then(function (list) {
                    box.textContent = '';
                    if (!list.length) { box.textContent = 'No product found.'; return; }
                    list.forEach(function (p) {
                      var a = document.createElement('a'); a.href = '#'; a.className = 'ad-link'; a.style.display = 'block';
                      a.textContent = p.title + (p.sku ? ' (' + p.sku + ')' : '') + ' — ৳ ' + p.price + ', stock ' + p.stock;
                      a.addEventListener('click', function (ev) { ev.preventDefault(); addRow(p); }); box.appendChild(a);
                    });
                  }).catch(function () { box.textContent = 'Search failed.'; });
              }, 250);
            });
            sum();
          })();
        </script>
      @endif
    </section>
  @endif

  {{-- ===== Refund ===== --}}
  @if (($claim->resolution === 'refund' || $excess > 0 || $refunded > 0) && in_array($claim->status, ['approved', 'resolved'], true))
    <section class="ad-card">
      <h2>Refund</h2>
      <dl class="ad-dl">
        <dt>Money held for order #{{ $order->id }}</dt><dd>৳ {{ number_format($held) }} <span class="ad-muted">(received minus refunds so far; this is the most you can refund)</span></dd>
        <dt>Refunded for this claim</dt><dd>৳ {{ number_format($refunded) }}</dd>
        <dt>Claimed goods are worth</dt><dd>৳ {{ number_format($claim->itemsValue()) }}</dd>
      </dl>
      @if ($approved && $canManage && $ax::can('payments'))
        <form action="{{ route('admin.claims.refund', $claim->id) }}" method="POST" class="ad-form" style="margin-top:10px">
          @csrf
          <div class="ad-row">
            <label>Amount (৳)
              <input type="number" name="amount" min="1" required value="{{ old('amount', $excess > 0 ? max(0, $excess - $refunded) : max(0, min($claim->itemsValue() - $refunded, $held))) }}">
            </label>
            <label>Method
              <select name="method">
                @foreach (\App\Models\Payment::methods() as $k => $label)
                  <option value="{{ $k }}" {{ old('method', 'bkash') === $k ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
              </select>
            </label>
            <label>Transaction ID <small>(optional)</small>
              <input type="text" name="trx_id" maxlength="100" value="{{ old('trx_id') }}">
            </label>
          </div>
          <label>Note <small>(optional)</small>
            <input type="text" name="note" maxlength="200" value="{{ old('note') }}">
          </label>
          <div class="ad-submit"><button type="submit" class="ad-btn" onclick="return confirm('Record this refund? It is added to the payments and to your money accounts.');">Record refund</button></div>
          <p class="ad-hint">Recorded on the original order as a refund (it follows the normal payments and accounting flow). The order total and invoice stay as they were, and the order does not show as "due" because of it.</p>
        </form>
      @elseif ($approved)
        <p class="ad-muted">Recording a refund needs the "Claims: manage" and "Payments" permissions.</p>
      @endif
    </section>
  @endif

  {{-- ===== Evidence ===== --}}
  <section class="ad-card">
    <h2>Evidence <small class="ad-muted">(private: only people with the claims permission can open these)</small></h2>
    @if ($claim->files->isEmpty())
      <p class="ad-muted">No photos or videos.</p>
    @else
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px">
        @foreach ($claim->files as $f)
          <div style="border:1px solid var(--line);border-radius:8px;padding:8px">
            @if ($f->isVideo())
              <video src="{{ route('admin.claims.evidence.show', [$claim->id, $f->id]) }}" controls preload="metadata" style="width:100%;max-height:200px;background:#000"></video>
            @else
              <a href="{{ route('admin.claims.evidence.show', [$claim->id, $f->id]) }}" target="_blank" rel="noopener"><img src="{{ route('admin.claims.evidence.show', [$claim->id, $f->id]) }}" alt="" loading="lazy" style="width:100%;max-height:200px;object-fit:contain"></a>
            @endif
            <small class="ad-muted" style="display:block;margin-top:4px">{{ $f->original_name }} · {{ $f->sizeLabel() }}<br>{{ $f->uploaded_by_name }}, {{ $f->created_at->format('d M Y') }}@if ($f->note)<br>{{ $f->note }}@endif</small>
            @if ($canManage && $claim->status !== 'resolved')
              <form action="{{ route('admin.claims.evidence.destroy', [$claim->id, $f->id]) }}" method="POST" onsubmit="return confirm('Remove this file for good?');">
                @csrf @method('DELETE')
                <button type="submit" class="ad-link ad-del">Remove</button>
              </form>
            @endif
          </div>
        @endforeach
      </div>
    @endif

    @if ($canManage && $claim->status !== 'resolved')
      <form action="{{ route('admin.claims.evidence.store', $claim->id) }}" method="POST" enctype="multipart/form-data" class="ad-form" style="margin-top:12px">
        @csrf
        <input type="file" name="files[]" multiple required accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/webm">
        <label>Note <small>(optional)</small><input type="text" name="note" maxlength="250"></label>
        <div class="ad-submit"><button type="submit" class="ad-btn ad-btn-gray">Add files</button></div>
        <p class="ad-hint">Photos up to 5 MB, videos up to 30 MB, {{ \App\Services\ClaimService::MAX_FILES }} files in total.</p>
      </form>
    @endif
  </section>

  {{-- ===== Shipments ===== --}}
  <section class="ad-card">
    <h2>Shipments</h2>
    @if ($claim->shipments->isEmpty())
      <p class="ad-muted">No shipment recorded.</p>
    @else
      <div class="ad-scroll">
        <table class="ad-table">
          <tr><th>Direction</th><th>Courier</th><th>Tracking</th><th>Status</th><th>Dates</th><th></th></tr>
          @foreach ($claim->shipments as $s)
            <tr>
              <td>{{ \App\Models\ServiceRequestShipment::directions()[$s->direction] ?? $s->direction }}</td>
              <td>{{ $s->courier ?: '—' }}</td>
              <td>{{ $s->tracking_code ?: '—' }}@if ($s->note)<br><small class="ad-muted">{{ $s->note }}</small>@endif</td>
              <td><span class="ad-st {{ \App\Models\ServiceRequestShipment::badge($s->status) }}">{{ \App\Models\ServiceRequestShipment::statuses()[$s->status] ?? $s->status }}</span></td>
              <td><small>@if ($s->shipped_at)Shipped {{ $s->shipped_at->format('d M Y') }}<br>@endif @if ($s->received_at)Received {{ $s->received_at->format('d M Y') }}@endif</small></td>
              <td>
                @if ($canManage && $claim->status !== 'resolved')
                  <form action="{{ route('admin.claims.shipments.update', [$claim->id, $s->id]) }}" method="POST" class="ad-filter" style="margin:0">
                    @csrf
                    <select name="status">
                      @foreach (\App\Models\ServiceRequestShipment::statuses() as $k => $label)
                        <option value="{{ $k }}" {{ $s->status === $k ? 'selected' : '' }}>{{ $label }}</option>
                      @endforeach
                    </select>
                    <button type="submit" class="ad-btn ad-btn-gray">Update</button>
                  </form>
                @endif
              </td>
            </tr>
          @endforeach
        </table>
      </div>
    @endif

    @if ($canManage && $claim->status !== 'resolved' && ($claim->status !== 'rejected' || ($isWarranty && $claim->inspected_at)))
      <form action="{{ route('admin.claims.shipments.store', $claim->id) }}" method="POST" class="ad-form" style="margin-top:12px">
        @csrf
        <div class="ad-row">
          <label>Direction
            <select name="direction">
              @foreach (\App\Models\ServiceRequestShipment::directions() as $k => $label)
                <option value="{{ $k }}">{{ $label }}</option>
              @endforeach
            </select>
          </label>
          <label>Courier <input type="text" name="courier" maxlength="60" placeholder="e.g. Steadfast, Sundarban"></label>
          <label>Tracking / consignment <input type="text" name="tracking_code" maxlength="100"></label>
          <label>Status
            <select name="status">
              @foreach (\App\Models\ServiceRequestShipment::statuses() as $k => $label)
                <option value="{{ $k }}" {{ $k === 'in_transit' ? 'selected' : '' }}>{{ $label }}</option>
              @endforeach
            </select>
          </label>
          <label>Shipped on <input type="date" name="shipped_at"></label>
        </div>
        <label>Note <small>(optional)</small><input type="text" name="note" maxlength="250"></label>
        <div class="ad-submit"><button type="submit" class="ad-btn ad-btn-gray">Add shipment</button></div>
        <p class="ad-hint">This only records the parcel (for example the defective goods coming back). A replacement or exchange order is sent with the normal "Send to Steadfast" button on the new order.</p>
      </form>
    @endif
  </section>

  {{-- ===== Close ===== --}}
  @if ($approved && $canManage)
    <section class="ad-card">
      <h2>Finish</h2>
      @if ($closeProblem)
        <p class="ad-muted">Not ready to close: {{ $closeProblem }}</p>
      @else
        <form action="{{ route('admin.claims.close', $claim->id) }}" method="POST" onsubmit="return confirm('Mark this claim as resolved? It cannot be changed afterwards.');">
          @csrf
          <button type="submit" class="ad-btn">✔ Mark claim as resolved</button>
        </form>
      @endif
    </section>
  @endif

  {{-- ===== History ===== --}}
  @if ($logs->isNotEmpty())
    <section class="ad-card">
      <h2>History</h2>
      <div class="ad-scroll">
        <table class="ad-table">
          @foreach ($logs as $l)
            <tr><td style="white-space:nowrap">{{ $l->created_at->format('d M Y, h:i A') }}</td><td>{{ $l->actor_name }}</td><td>{{ $l->summary }}</td></tr>
          @endforeach
        </table>
      </div>
    </section>
  @endif
@endsection
