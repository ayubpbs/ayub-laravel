<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>{{ $invoices->count() === 1 ? $invoices->first()->number() : 'Invoices' }} — {{ config('shop.name') }}</title>
  <style>
    *{ box-sizing:border-box; }
    body{ margin:0; background:#e5e7eb; color:#111; font-family:"Hind Siliguri","Noto Sans Bengali","Segoe UI",Tahoma,sans-serif; font-size:14px; line-height:1.45; }
    .bar{ position:sticky; top:0; z-index:5; background:#1f2937; color:#fff; padding:10px 16px; display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
    .bar button, .bar a{ background:#ed145b; color:#fff; border:0; border-radius:6px; padding:7px 14px; font:inherit; font-weight:700; cursor:pointer; text-decoration:none; }
    .bar .alt{ background:#374151; }
    .bar .alt.on{ background:#fff; color:#111; }
    .bar input{ flex:1; min-width:180px; max-width:420px; padding:6px 10px; border-radius:6px; border:0; font:inherit; font-size:13px; }
    .bar .sp{ flex:1; }

    .sheet{ position:relative; background:#fff; width:210mm; min-height:140mm; margin:16px auto; padding:14mm 14mm 12mm; box-shadow:0 2px 12px rgba(0,0,0,.15); page-break-after:always; overflow:hidden; }
    .sheet:last-of-type{ page-break-after:auto; }

    .head{ display:flex; justify-content:space-between; gap:20px; border-bottom:2px solid #111; padding-bottom:10px; margin-bottom:14px; }
    .shop h1{ margin:0; font-size:22px; }
    .shop p{ margin:1px 0; color:#444; font-size:13px; }
    .meta{ text-align:right; }
    .meta h2{ margin:0 0 4px; font-size:20px; letter-spacing:.06em; }
    .meta p{ margin:1px 0; font-size:13px; }

    .two{ display:flex; gap:24px; margin-bottom:14px; }
    .two > div{ flex:1; }
    .lbl{ font-size:11.5px; text-transform:uppercase; letter-spacing:.05em; color:#666; margin-bottom:2px; }

    table{ width:100%; border-collapse:collapse; }
    .items th{ text-align:left; background:#f3f4f6; font-size:12.5px; padding:7px 8px; border-top:1px solid #ccc; border-bottom:1px solid #ccc; }
    .items td{ padding:7px 8px; border-bottom:1px solid #e5e5e5; vertical-align:top; }
    .r{ text-align:right; } .c{ text-align:center; }

    .totals{ width:320px; margin:12px 0 0 auto; }
    .totals td{ padding:4px 8px; }
    .totals .grand td{ border-top:2px solid #111; font-weight:700; font-size:16px; padding-top:7px; }
    .totals .due td{ font-weight:700; color:#b91c1c; }

    .pays{ margin-top:14px; font-size:13px; }
    .pays table td, .pays table th{ padding:3px 8px; text-align:left; border-bottom:1px dashed #ddd; }
    .pays table th{ font-size:11.5px; color:#666; text-transform:uppercase; }

    .note{ margin-top:14px; font-size:13px; color:#444; }
    .foot{ margin-top:18px; padding-top:8px; border-top:1px solid #ccc; text-align:center; color:#555; font-size:12.5px; }

    .stamp{ position:absolute; top:46mm; right:16mm; transform:rotate(-12deg); border:3px solid; border-radius:8px; padding:2px 16px; font-size:30px; font-weight:800; letter-spacing:.08em; opacity:.22; pointer-events:none; }
    .stamp.paid{ color:#15803d; } .stamp.void{ color:#b91c1c; }

    /* 80 mm receipt printer layout */
    body.receipt .sheet{ width:80mm; min-height:0; padding:4mm 3mm; margin:12px auto; font-size:12px; }
    body.receipt .head{ display:block; text-align:center; border-bottom:1px dashed #000; padding-bottom:6px; margin-bottom:8px; }
    body.receipt .shop h1{ font-size:17px; }
    body.receipt .meta{ text-align:center; margin-top:6px; }
    body.receipt .meta h2{ font-size:15px; }
    body.receipt .two{ display:block; margin-bottom:8px; }
    body.receipt .two > div{ margin-bottom:6px; }
    body.receipt .items th:nth-child(1), body.receipt .items td:nth-child(1){ display:none; }
    body.receipt .items th, body.receipt .items td{ padding:4px 2px; }
    body.receipt .totals{ width:100%; }
    body.receipt .totals .grand td{ font-size:14px; }
    body.receipt .stamp{ top:30mm; right:4mm; font-size:20px; }
    body.receipt .pays th:nth-child(3), body.receipt .pays td:nth-child(3){ display:none; }

    @media (max-width:800px){ .sheet{ width:auto; margin:10px; padding:14px; } body.receipt .sheet{ width:auto; max-width:80mm; margin:10px auto; } }
    @media print{
      body{ background:#fff; }
      .bar{ display:none !important; }
      .sheet{ box-shadow:none; margin:0; width:auto; }
      body.receipt .sheet{ width:80mm; }
    }
  </style>
  <style id="pageStyle">@page{ size:A4; margin:8mm; }</style>
</head>
<body>

<div class="bar">
  <button type="button" onclick="window.print()">🖨 Print / Save as PDF</button>
  <button type="button" class="alt on" id="btnA4">A4</button>
  <button type="button" class="alt" id="btnReceipt">Receipt (80 mm)</button>
  <span class="sp"></span>
  @if (!$public)
    @if ($link)
      <input type="text" id="shareLink" value="{{ $link }}" readonly onclick="this.select()" aria-label="Customer link">
      <button type="button" class="alt" id="btnCopy">Copy customer link</button>
    @endif
    <a class="alt" href="{{ route('admin.invoices.index') }}">← Invoices</a>
  @endif
</div>

@foreach ($invoices as $inv)
  @php
    $o      = $inv->order;
    $net    = $o->netPaid();
    $due    = $o->dueAmount();
    $status = $o->paymentStatus();
    $stamp  = $o->isLocked() ? ['void', strtoupper($o->statusLabel())] : ($status === 'paid' ? ['paid', 'PAID'] : null);
  @endphp
  <div class="sheet">
    @if ($stamp)<div class="stamp {{ $stamp[0] }}">{{ $stamp[1] }}</div>@endif

    <div class="head">
      <div class="shop">
        <h1>{{ config('shop.name') }}</h1>
        @if (config('shop.tagline'))<p>{{ config('shop.tagline') }}</p>@endif
        @if (config('shop.address'))<p>{{ config('shop.address') }}</p>@endif
        @if (config('shop.phone'))<p>Phone: {{ config('shop.phone') }}</p>@endif
        @if (config('shop.email'))<p>{{ config('shop.email') }}</p>@endif
      </div>
      <div class="meta">
        <h2>INVOICE</h2>
        <p><strong>{{ $inv->number() }}</strong></p>
        <p>Date: {{ $inv->issued_at->format('d M Y') }}</p>
        <p>Order: #{{ $o->id }} ({{ $o->created_at->format('d M Y') }})</p>
      </div>
    </div>

    <div class="two">
      <div>
        <div class="lbl">Bill to</div>
        <strong>{{ $o->name }}</strong><br>
        {{ $o->phone }}<br>
        {{ $o->address }}
      </div>
      <div>
        <div class="lbl">Payment</div>
        <strong>{{ $o->paymentLabel() }}</strong>
        @if ($o->tracking_code)<br>Courier tracking: {{ $o->tracking_code }}@endif
      </div>
    </div>

    <table class="items">
      <tr><th style="width:28px">#</th><th>Item</th><th class="r">Price</th><th class="c">Qty</th><th class="r">Total</th></tr>
      @foreach ($o->items as $i => $item)
        <tr>
          <td>{{ $i + 1 }}</td>
          <td>{{ $item->title }}</td>
          <td class="r">৳ {{ number_format($item->price) }}</td>
          <td class="c">{{ $item->quantity }}</td>
          <td class="r">৳ {{ number_format($item->line_total) }}</td>
        </tr>
      @endforeach
    </table>

    <table class="totals">
      <tr><td>Subtotal</td><td class="r">৳ {{ number_format($o->subtotal) }}</td></tr>
      <tr><td>Delivery charge</td><td class="r">৳ {{ number_format($o->delivery_charge) }}</td></tr>
      <tr class="grand"><td>Total</td><td class="r">৳ {{ number_format($o->total) }}</td></tr>
      <tr><td>Paid</td><td class="r">৳ {{ number_format(max(0, $net)) }}</td></tr>
      @if ($due > 0)
        <tr class="due"><td>Due{{ $o->status !== 'delivered' ? ' (cash on delivery)' : '' }}</td><td class="r">৳ {{ number_format($due) }}</td></tr>
      @endif
    </table>

    @if ($o->payments->isNotEmpty())
      <div class="pays">
        <div class="lbl">Payments</div>
        <table>
          <tr><th>Date</th><th>Method</th><th>Ref</th><th class="r">Amount</th></tr>
          @foreach ($o->payments->sortBy('paid_at') as $p)
            <tr>
              <td>{{ $p->paid_at->format('d M Y') }}</td>
              <td>{{ $p->methodLabel() }}{{ $p->isRefund() ? ' (refund)' : '' }}</td>
              <td>{{ $p->trx_id ?: '' }}</td>
              <td class="r">{{ $p->isRefund() ? '−' : '' }}৳ {{ number_format($p->amount) }}</td>
            </tr>
          @endforeach
        </table>
      </div>
    @endif

    @if ($inv->note || $o->note)
      <div class="note">
        @if ($inv->note)<div><strong>Note:</strong> {{ $inv->note }}</div>@endif
        @if ($o->note)<div><strong>Customer note:</strong> {{ $o->note }}</div>@endif
      </div>
    @endif

    <div class="foot">{{ config('shop.invoice_note') }}</div>
  </div>
@endforeach

<script>
  (function () {
    var page = document.getElementById('pageStyle');
    var a4 = document.getElementById('btnA4'), rc = document.getElementById('btnReceipt');
    function setMode(receipt) {
      document.body.classList.toggle('receipt', receipt);
      a4.classList.toggle('on', !receipt);
      rc.classList.toggle('on', receipt);
      page.textContent = receipt ? '@page{ size:80mm auto; margin:2mm; }' : '@page{ size:A4; margin:8mm; }';
      try { localStorage.setItem('invoiceMode', receipt ? 'receipt' : 'a4'); } catch (e) {}
    }
    a4.addEventListener('click', function () { setMode(false); });
    rc.addEventListener('click', function () { setMode(true); });
    try { if (localStorage.getItem('invoiceMode') === 'receipt') setMode(true); } catch (e) {}

    var copy = document.getElementById('btnCopy');
    if (copy) copy.addEventListener('click', function () {
      var box = document.getElementById('shareLink');
      box.select();
      (navigator.clipboard ? navigator.clipboard.writeText(box.value) : Promise.reject()).then(function () { copy.textContent = 'Copied ✓'; }, function () { document.execCommand('copy'); copy.textContent = 'Copied ✓'; });
    });
  })();
</script>
</body>
</html>
