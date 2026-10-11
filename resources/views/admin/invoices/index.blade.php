@extends('admin.layout')

@section('title', 'Invoices')

@section('content')
  <div class="ad-bar">
    <h1 class="ad-title">Invoices</h1>
    @if ($stats['missing'] > 0)
      <form action="{{ route('admin.invoices.generate.missing') }}" method="POST">
        @csrf
        <button type="submit" class="ad-btn">Create {{ $stats['missing'] }} missing invoice(s)</button>
      </form>
    @endif
  </div>

  <div class="ad-stats">
    <div class="ad-stat"><span class="n">{{ $stats['total'] }}</span><span class="l">Total invoices</span></div>
    <div class="ad-stat"><span class="n">{{ $stats['month'] }}</span><span class="l">Issued this month</span></div>
    <a class="ad-stat {{ $stats['unpaid'] ? 'ad-stat-hot' : '' }}" href="{{ route('admin.invoices.index', ['pay' => 'unpaid']) }}"><span class="n">{{ $stats['unpaid'] }}</span><span class="l">Unpaid invoices</span></a>
  </div>

  <form class="ad-filter" method="GET" action="{{ route('admin.invoices.index') }}">
    <input type="text" name="q" value="{{ $q }}" placeholder="Invoice no., order no., name or mobile…">
    <select name="pay">
      <option value="">Any payment status</option>
      <option value="paid"    {{ $pay === 'paid'    ? 'selected' : '' }}>Paid</option>
      <option value="partial" {{ $pay === 'partial' ? 'selected' : '' }}>Partly paid</option>
      <option value="unpaid"  {{ $pay === 'unpaid'  ? 'selected' : '' }}>Unpaid</option>
    </select>
    <input type="date" name="from" value="{{ $from }}" title="From" style="flex:none;min-width:150px;width:160px">
    <input type="date" name="to" value="{{ $to }}" title="To" style="flex:none;min-width:150px;width:160px">
    <button type="submit" class="ad-btn ad-btn-gray">Filter</button>
    @if ($q !== '' || $pay !== '' || $from !== '' || $to !== '')<a class="ad-btn ad-btn-gray" href="{{ route('admin.invoices.index') }}">Clear</a>@endif
  </form>

  <section class="ad-card">
    @if ($invoices->isEmpty())
      <p class="ad-muted">No invoices found. They are created automatically when an order is confirmed.</p>
    @else
      <form method="GET" action="{{ route('admin.invoices.print') }}" id="bulkPrint" target="_blank">
        <input type="hidden" name="ids" id="bulkIds">
        <div class="ad-scroll">
          <table class="ad-table">
            <tr><th><input type="checkbox" id="checkAll" aria-label="Select all"></th><th>Invoice</th><th>Order</th><th>Date</th><th>Customer</th><th class="r">Total</th><th class="r">Due</th><th>Payment</th><th></th></tr>
            @foreach ($invoices as $inv)
              @php $o = $inv->order; @endphp
              <tr>
                <td><input type="checkbox" class="rowCheck" value="{{ $inv->id }}"></td>
                <td><a href="{{ route('admin.invoices.show', $inv->id) }}"><strong>{{ $inv->number() }}</strong></a></td>
                <td><a href="{{ route('admin.orders.show', $o->id) }}">#{{ $o->id }}</a></td>
                <td>{{ $inv->issued_at->format('d M Y') }}</td>
                <td>{{ $o->name }}<br><small class="ad-muted">{{ $o->phone }}</small></td>
                <td class="r">৳ {{ number_format($o->total) }}</td>
                <td class="r {{ $o->dueAmount() > 0 ? 'ad-red' : '' }}">৳ {{ number_format($o->dueAmount()) }}</td>
                <td><span class="ad-st {{ \App\Models\Order::paymentBadge($o->paymentStatus()) }}">{{ $o->paymentLabel() }}</span>@if ($o->isLocked())<br><span class="ad-st st-{{ $o->status }}">{{ $o->statusLabel() }}</span>@endif</td>
                <td class="ad-actions"><a class="ad-link" href="{{ route('admin.invoices.show', $inv->id) }}" target="_blank">Print</a></td>
              </tr>
            @endforeach
          </table>
        </div>
        <div class="ad-submit" style="margin-top:12px">
          <button type="submit" class="ad-btn ad-btn-gray">🖨 Print ticked invoices (max 50)</button>
        </div>
      </form>
      <div class="ad-pager">{{ $invoices->links('pagination::simple-default') }}</div>
      <script>
        document.getElementById('checkAll').addEventListener('change', function () {
          document.querySelectorAll('.rowCheck').forEach(function (c) { c.checked = this.checked; }, this);
        });
        document.getElementById('bulkPrint').addEventListener('submit', function (e) {
          var ids = Array.prototype.map.call(document.querySelectorAll('.rowCheck:checked'), function (c) { return c.value; });
          if (!ids.length) { e.preventDefault(); alert('Please tick at least one invoice.'); return; }
          document.getElementById('bulkIds').value = ids.join(',');
        });
      </script>
    @endif
  </section>
@endsection
