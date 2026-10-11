@extends('admin.layout')

@section('title', 'Payment Receive')

@section('content')
  <div class="ad-bar">
    <h1 class="ad-title">📥 Payment Receive</h1>
    <a class="ad-btn" href="{{ route('admin.payments.create') }}">＋ Record customer payment</a>
  </div>

  <div class="ad-stats">
    <div class="ad-stat {{ $cod > 0 ? 'ad-stat-hot' : '' }}"><span class="n">৳ {{ number_format($cod) }}</span><span class="l">Courier (COD) money waiting</span></div>
    <div class="ad-stat"><span class="n">৳ {{ number_format($other) }}</span><span class="l">Other payments waiting for an account</span></div>
    <div class="ad-stat"><span class="n {{ $refunds > 0 ? 'ad-red' : '' }}">৳ {{ number_format($refunds) }}</span><span class="l">Refunds waiting for an account</span></div>
  </div>

  <section class="ad-card">
    <h2>Waiting to be added to an account</h2>
    @if (!$ready)
      <p class="ad-muted">Please run the new migration first (<code>php artisan migrate</code>). After that, new customer payments are linked to the accounts.</p>
    @elseif ($pending->isEmpty())
      <p class="ad-muted">Nothing is waiting. Every payment recorded since the link was switched on is already in an account.</p>
    @else
      <form action="{{ route('admin.accounting.customers.receive.store') }}" method="POST"
            onsubmit="return confirm('Add the ticked payments to the chosen account? This changes its balance.');">
        @csrf
        <div class="ad-scroll">
          <table class="ad-table">
            <tr><th><input type="checkbox" id="checkAll" aria-label="Select all"></th><th>Date</th><th>Order</th><th>Customer</th><th>Method</th><th class="r">Amount</th></tr>
            @foreach ($pending as $p)
              <tr>
                <td><input type="checkbox" name="ids[]" value="{{ $p->id }}" class="rowCheck" data-cod="{{ $p->method === 'cod' && !$p->isRefund() ? 1 : 0 }}" {{ is_array(old('ids')) && in_array($p->id, old('ids')) ? 'checked' : '' }}></td>
                <td>{{ $p->paid_at->format('d M Y') }}</td>
                <td><a href="{{ route('admin.orders.show', $p->order_id) }}"><strong>#{{ $p->order_id }}</strong></a></td>
                <td>{{ optional($p->order)->name }}<br><small class="ad-muted">{{ optional($p->order)->phone }}</small></td>
                <td>{{ $p->methodLabel() }} @if ($p->isRefund())<span class="ad-st st-returned">Refund</span>@endif @if ($p->trx_id)<br><small class="ad-muted">{{ $p->trx_id }}</small>@endif</td>
                <td class="r {{ $p->isRefund() ? 'ad-red' : '' }}"><strong>{{ $p->isRefund() ? '−' : '' }}৳ {{ number_format($p->amount) }}</strong></td>
              </tr>
            @endforeach
          </table>
        </div>

        <div class="ad-form" style="margin-top:14px">
          <div class="ad-row">
            <label>Money goes into / comes out of
              <select name="account_id" required>
                <option value="">— Choose account —</option>
                @foreach ($accounts as $a)
                  <option value="{{ $a['id'] }}" {{ (string) old('account_id', $defaultAccount ?: '') === (string) $a['id'] ? 'selected' : '' }}>{{ $a['label'] }}</option>
                @endforeach
              </select>
            </label>
            <label>Date the money reached you
              <input type="date" name="txn_date" id="txnDate" value="{{ old('txn_date', now()->toDateString()) }}" required>
            </label>
          </div>
          <div class="ad-row">
            <label>Courier charge deducted (৳) <small>(optional)</small>
              <input type="number" name="courier_charge" id="courierCharge" value="{{ old('courier_charge') }}" min="0" step="0.01" data-per="{{ $courierCharge }}">
            </label>
          </div>
          <div class="ad-submit">
            <button type="submit" class="ad-btn">Add ticked payments to account</button>
          </div>
        </div>
        <p class="ad-hint">When the courier pays you, tick the orders they paid for and choose where it arrived (Cash, bKash, a bank…). Payments in cash, bKash, Nagad (and a single bank) are added to their account automatically and do not appear here.</p>
      </form>
      <script>
        document.getElementById('checkAll').addEventListener('change', function () {
          document.querySelectorAll('.rowCheck').forEach(function (c) { c.checked = this.checked; }, this);
        });
        (function () {
          // suggest the courier charge: ticked COD orders × charge per order (set in Accounting Settings)
          var box = document.getElementById('courierCharge'), per = Number(box.dataset.per || 0), touched = {{ old('courier_charge') !== null ? 'true' : 'false' }};
          box.addEventListener('input', function () { touched = true; });
          function suggest() {
            if (touched || !per) return;
            var n = document.querySelectorAll('.rowCheck[data-cod="1"]:checked').length;
            box.value = n ? n * per : '';
          }
          document.querySelectorAll('.rowCheck, #checkAll').forEach(function (c) { c.addEventListener('change', suggest); });
          var d = document.getElementById('txnDate');
          if (d && !{{ old('txn_date') ? 'true' : 'false' }}) {
            d.value = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);
          }
        })();
      </script>
    @endif
  </section>

  @if ($recent->isNotEmpty())
    <section class="ad-card">
      <h2>Recently added to accounts</h2>
      <div class="ad-scroll">
        <table class="ad-table">
          <tr><th>Date</th><th>Account</th><th>Note</th><th class="r">Amount</th></tr>
          @foreach ($recent as $t)
            <tr>
              <td>{{ $t->txn_date->format('d M Y') }}</td>
              <td>{{ optional($t->account)->name }}</td>
              <td>{{ $t->note }}</td>
              <td class="r {{ $t->isIn() ? '' : 'ad-red' }}"><strong>{{ $t->isIn() ? '' : '−' }}৳ {{ number_format($t->amount, 2) }}</strong></td>
            </tr>
          @endforeach
        </table>
      </div>
    </section>
  @endif
@endsection
