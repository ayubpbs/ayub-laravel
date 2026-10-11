@extends('admin.layout')

@section('title', 'Payments')

@section('content')
  <div class="ad-bar">
    <h1 class="ad-title">Payments</h1>
    <a class="ad-btn" href="{{ route('admin.payments.create') }}">＋ Record Payment</a>
  </div>

  <div class="ad-stats">
    <div class="ad-stat"><span class="n">৳ {{ number_format($summary['today']) }}</span><span class="l">Received today</span></div>
    <div class="ad-stat"><span class="n">৳ {{ number_format($summary['month']) }}</span><span class="l">Received this month</span></div>
    <a class="ad-stat {{ $summary['due'] > 0 ? 'ad-stat-hot' : '' }}" href="{{ route('admin.payments.index', ['tab' => 'due']) }}">
      <span class="n">৳ {{ number_format($summary['due']) }}</span><span class="l">Still to collect ({{ $summary['due_orders'] }} orders)</span>
    </a>
    <div class="ad-stat"><span class="n {{ $summary['refund_due'] > 0 ? 'ad-red' : '' }}">৳ {{ number_format($summary['refund_due']) }}</span><span class="l">To refund (cancelled / returned)</span></div>
  </div>

  <div class="ad-tabs">
    <a href="{{ route('admin.payments.index') }}" class="{{ $tab === 'payments' ? 'on' : '' }}">All payments</a>
    <a href="{{ route('admin.payments.index', ['tab' => 'due']) }}" class="{{ $tab === 'due' ? 'on' : '' }}">Due orders ({{ $summary['due_orders'] }})</a>
  </div>

  @if ($tab === 'payments')
    <form class="ad-filter" method="GET" action="{{ route('admin.payments.index') }}">
      <input type="text" name="q" value="{{ $f['q'] }}" placeholder="Order no., name, mobile or transaction ID…">
      <select name="method">
        <option value="">All methods</option>
        @foreach (\App\Models\Payment::methods() as $k => $label)
          <option value="{{ $k }}" {{ $f['method'] === $k ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
      </select>
      <select name="type">
        <option value="">Payments &amp; refunds</option>
        <option value="payment" {{ $f['type'] === 'payment' ? 'selected' : '' }}>Payments only</option>
        <option value="refund"  {{ $f['type'] === 'refund'  ? 'selected' : '' }}>Refunds only</option>
      </select>
      <input type="date" name="from" value="{{ $f['from'] }}" title="From" style="flex:none;min-width:150px;width:160px">
      <input type="date" name="to" value="{{ $f['to'] }}" title="To" style="flex:none;min-width:150px;width:160px">
      <button type="submit" class="ad-btn ad-btn-gray">Filter</button>
      @if (collect($f)->filter()->isNotEmpty())<a class="ad-btn ad-btn-gray" href="{{ route('admin.payments.index') }}">Clear</a>@endif
      <a class="ad-btn ad-btn-gray" href="{{ route('admin.payments.export', array_filter($f)) }}">⬇ CSV</a>
    </form>

    <section class="ad-card">
      @if ($payments->isEmpty())
        <p class="ad-muted">No payments found.</p>
      @else
        <div class="ad-scroll">
          <table class="ad-table">
            <tr><th>Date</th><th>Order</th><th>Customer</th><th>Method</th><th>Transaction</th><th class="r">Amount</th><th></th></tr>
            @foreach ($payments as $p)
              <tr>
                <td>{{ $p->paid_at->format('d M Y, h:i A') }}</td>
                <td><a href="{{ route('admin.orders.show', $p->order_id) }}"><strong>#{{ $p->order_id }}</strong></a></td>
                <td>{{ optional($p->order)->name }}<br><small class="ad-muted">{{ optional($p->order)->phone }}</small></td>
                <td>{{ $p->methodLabel() }} @if ($p->isRefund())<span class="ad-st st-returned">Refund</span>@endif</td>
                <td>{{ $p->trx_id ?: '—' }}@if ($p->sender_number)<br><small class="ad-muted">from {{ $p->sender_number }}</small>@endif @if ($p->note)<br><small class="ad-muted">{{ $p->note }}</small>@endif</td>
                <td class="r {{ $p->isRefund() ? 'ad-red' : '' }}"><strong>{{ $p->isRefund() ? '−' : '' }}৳ {{ number_format($p->amount) }}</strong></td>
                <td>
                  <form action="{{ route('admin.payments.destroy', $p->id) }}" method="POST" onsubmit="return confirm('Delete this payment record? The order\'s due amount will change.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="ad-link ad-del">Delete</button>
                  </form>
                </td>
              </tr>
            @endforeach
          </table>
        </div>
        <div class="ad-pager">{{ $payments->links('pagination::simple-default') }}</div>
      @endif
    </section>
  @else
    <form class="ad-filter" method="GET" action="{{ route('admin.payments.index') }}">
      <input type="hidden" name="tab" value="due">
      <input type="text" name="q" value="{{ $q }}" placeholder="Order no., name or mobile…">
      <select name="status">
        <option value="">All order statuses</option>
        @foreach (\App\Models\Order::statuses() as $k => $label)
          @unless (in_array($k, \App\Models\Order::lockedStatuses()))
            <option value="{{ $k }}" {{ $status === $k ? 'selected' : '' }}>{{ $label }}</option>
          @endunless
        @endforeach
      </select>
      <button type="submit" class="ad-btn ad-btn-gray">Filter</button>
      @if ($q !== '' || $status !== '')<a class="ad-btn ad-btn-gray" href="{{ route('admin.payments.index', ['tab' => 'due']) }}">Clear</a>@endif
    </form>

    <section class="ad-card">
      @if ($orders->isEmpty())
        <p class="ad-muted">Nothing is due. Every live order is fully paid.</p>
      @else
        <form method="POST" action="{{ route('admin.payments.collect.bulk') }}"
              onsubmit="return confirm(event.submitter && event.submitter.dataset.one ? 'Record the full due amount of this order as cash on delivery collected?' : 'Record the full due amount of the ticked orders as cash on delivery collected?');">
          @csrf
          <div class="ad-scroll">
            <table class="ad-table">
              <tr><th><input type="checkbox" id="checkAll" aria-label="Select all"></th><th>Order</th><th>Customer</th><th>Status</th><th class="r">Total</th><th class="r">Paid</th><th class="r">Due</th><th></th></tr>
              @foreach ($orders as $o)
                <tr>
                  <td><input type="checkbox" name="ids[]" value="{{ $o->id }}" class="rowCheck"></td>
                  <td><a href="{{ route('admin.orders.show', $o->id) }}"><strong>#{{ $o->id }}</strong></a><br><small class="ad-muted">{{ $o->created_at->format('d M') }}</small></td>
                  <td>{{ $o->name }}<br><small class="ad-muted">{{ $o->phone }}</small></td>
                  <td><span class="ad-st st-{{ $o->status }}">{{ $o->statusLabel() }}</span></td>
                  <td class="r">৳ {{ number_format($o->total) }}</td>
                  <td class="r">৳ {{ number_format($o->netPaid()) }}</td>
                  <td class="r ad-red"><strong>৳ {{ number_format($o->dueAmount()) }}</strong></td>
                  <td class="ad-actions">
                    <button type="submit" class="ad-link" data-one="1" formaction="{{ route('admin.payments.collect', $o->id) }}">Mark paid</button>
                    <a class="ad-link" href="{{ route('admin.payments.create', ['order' => $o->id]) }}">Part payment</a>
                  </td>
                </tr>
              @endforeach
            </table>
          </div>
          <div class="ad-submit" style="margin-top:12px">
            <button type="submit" class="ad-btn ad-btn-gray">Mark ticked orders as paid (cash on delivery)</button>
          </div>
        </form>
        <div class="ad-pager">{{ $orders->links('pagination::simple-default') }}</div>
        <script>
          document.getElementById('checkAll').addEventListener('change', function () {
            document.querySelectorAll('.rowCheck').forEach(function (c) { c.checked = this.checked; }, this);
          });
        </script>
        <p class="ad-hint">"Mark paid" records the full remaining amount as cash on delivery. Use it once the money has reached you (or after Steadfast has paid you out).</p>
      @endif
    </section>
  @endif
@endsection
