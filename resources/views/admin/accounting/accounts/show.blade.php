@extends('admin.layout')

@section('title', $title)

@section('content')
  @if (!empty($backUrl))
    <p class="ad-crumbs"><a href="{{ $backUrl }}">← {{ $backLabel }}</a></p>
  @endif
  <h1 class="ad-title">{{ $heading }}</h1>
  @if (!empty($subtitle))
    <p class="ad-muted" style="margin:-8px 0 14px">{{ $subtitle }}</p>
  @endif

  @include('admin.accounting.accounts._tabs', ['active' => $active])

  <div class="ad-stats">
    <div class="ad-stat"><span class="n {{ $totals['balance'] < 0 ? 'ad-red' : '' }}">৳ {{ number_format($totals['balance'], 2) }}</span><span class="l">Current balance</span></div>
    <div class="ad-stat"><span class="n">৳ {{ number_format((float) $account->opening_balance, 2) }}</span><span class="l">Opening balance</span></div>
    <div class="ad-stat"><span class="n ad-green">৳ {{ number_format($totals['in'], 2) }}</span><span class="l">Total money in</span></div>
    <div class="ad-stat"><span class="n ad-red">৳ {{ number_format($totals['out'], 2) }}</span><span class="l">Total money out</span></div>
  </div>

  <div class="ad-two">
    <section class="ad-card">
      <h2>Add entry</h2>
      <form class="ad-form" action="{{ $urlBase }}/entries" method="POST">
        @csrf
        <div class="ad-row">
          <label>Type
            <select name="direction" required>
              <option value="in"  {{ old('direction', 'in') === 'in' ? 'selected' : '' }}>Money In (received)</option>
              <option value="out" {{ old('direction') === 'out' ? 'selected' : '' }}>Money Out (paid)</option>
            </select>
          </label>
          <label>Amount (৳)
            <input type="number" name="amount" value="{{ old('amount') }}" step="0.01" min="0.01" required>
          </label>
        </div>
        <div class="ad-row">
          <label>Date
            <input type="date" name="txn_date" id="txnDate" value="{{ old('txn_date', now()->toDateString()) }}" required>
          </label>
          <label>Note <small>(optional)</small>
            <input type="text" name="note" value="{{ old('note') }}" maxlength="255" placeholder="e.g. Shop rent, customer payment…">
          </label>
        </div>
        <div class="ad-submit"><button type="submit" class="ad-btn">Save entry</button></div>
      </form>
    </section>

    <section class="ad-card">
      <h2>Opening balance</h2>
      <form class="ad-form" action="{{ $urlBase }}/opening" method="POST">
        @csrf
        <label>Balance before the first entry (৳)
          <input type="number" name="opening_balance" value="{{ old('opening_balance', (float) $account->opening_balance) }}" step="0.01" min="0" required>
        </label>
        <div class="ad-submit"><button type="submit" class="ad-btn ad-btn-gray">Save opening balance</button></div>
      </form>
      <p class="ad-hint">Set this once, to the money this account already held when you started using this page.</p>
    </section>
  </div>

  <section class="ad-card">
    <div class="ad-card-head"><h2>Transactions</h2></div>

    <form class="ad-filter" method="GET" action="{{ $urlBase }}">
      <label>From <input type="date" name="from" value="{{ $f['from'] }}"></label>
      <label>To <input type="date" name="to" value="{{ $f['to'] }}"></label>
      <select name="dir">
        <option value="">Money in &amp; out</option>
        <option value="in"  {{ $f['dir'] === 'in'  ? 'selected' : '' }}>Money in only</option>
        <option value="out" {{ $f['dir'] === 'out' ? 'selected' : '' }}>Money out only</option>
      </select>
      <button type="submit" class="ad-btn ad-btn-gray">Filter</button>
      @if ($filtered)
        <a class="ad-btn ad-btn-gray" href="{{ $urlBase }}">Reset</a>
      @endif
    </form>

    @if ($transactions->isEmpty())
      <p class="ad-muted">No entries found.</p>
    @else
      <div class="ad-scroll">
        <table class="ad-table">
          <tr><th>Date</th><th>Note</th><th class="r">Money in</th><th class="r">Money out</th><th></th></tr>
          @foreach ($transactions as $tx)
            <tr>
              <td>{{ $tx->txn_date->format('d M Y') }}</td>
              <td>{{ $tx->note ?: '—' }}@if ($tx->source !== 'manual') <span class="ad-muted">({{ \App\Http\Controllers\Admin\Accounting\LedgerController::sources()[$tx->source] ?? ucfirst($tx->source) }})</span>@endif</td>
              <td class="r">@if ($tx->isIn())<span class="ad-green">৳ {{ number_format((float) $tx->amount, 2) }}</span>@else — @endif</td>
              <td class="r">@if (!$tx->isIn())<span class="ad-red">৳ {{ number_format((float) $tx->amount, 2) }}</span>@else — @endif</td>
              <td class="ad-actions">
                @if ($tx->source === 'manual')
                  <form action="{{ $urlBase }}/entries/{{ $tx->id }}" method="POST" onsubmit="return confirm('Delete this entry?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="ad-link ad-del">Delete</button>
                  </form>
                @endif
              </td>
            </tr>
          @endforeach
          @if ($filtered)
            <tr class="ad-tfoot">
              <td colspan="2">Total of this filtered list</td>
              <td class="r">৳ {{ number_format($period['in'], 2) }}</td>
              <td class="r">৳ {{ number_format($period['out'], 2) }}</td>
              <td></td>
            </tr>
          @endif
        </table>
      </div>
      <div class="ad-pager">{{ $transactions->links('pagination::simple-default') }}</div>
    @endif
  </section>

  <script>
    // Default the date to the browser's local day (the server clock may be in another time zone)
    (function () {
      var d = document.getElementById('txnDate');
      if (d && !{{ old('txn_date') ? 'true' : 'false' }}) {
        d.value = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);
      }
    })();
  </script>
@endsection
