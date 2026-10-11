@extends('admin.layout')

@section('title', 'Accounting Dashboard')

@section('content')
  @php
    $accUrl  = fn ($a) => $a->type === 'bank'
        ? route('admin.accounting.banks.show', $a->id)
        : route('admin.accounting.accounts.show', $a->type);
    $accName = fn ($a) => ($a->type === 'bank' ? '🏦' : (\App\Models\Account::types()[$a->type][1] ?? '')) . ' ' . $a->name;
    $net     = $periodIn - $periodOut;
  @endphp

  <h1 class="ad-title">💰 Accounting Dashboard</h1>

  {{-- ===== Where the money is now ===== --}}
  <div class="ad-stats">
    <div class="ad-stat ad-stat-hot">
      <span class="n">৳ {{ number_format($total, 2) }}</span>
      <span class="l">Total balance (all accounts)</span>
    </div>
    <a class="ad-stat" href="{{ route('admin.accounting.accounts.show', 'cash') }}">
      <span class="n {{ $cash < 0 ? 'ad-red' : '' }}">৳ {{ number_format($cash, 2) }}</span><span class="l">💵 Cash</span>
    </a>
    <a class="ad-stat" href="{{ route('admin.accounting.banks.index') }}">
      <span class="n {{ $bank < 0 ? 'ad-red' : '' }}">৳ {{ number_format($bank, 2) }}</span><span class="l">🏦 Banks ({{ $bankCount }})</span>
    </a>
    <a class="ad-stat" href="{{ route('admin.accounting.accounts.show', 'bkash') }}">
      <span class="n {{ $bkash < 0 ? 'ad-red' : '' }}">৳ {{ number_format($bkash, 2) }}</span><span class="l">📱 bKash</span>
    </a>
    <a class="ad-stat" href="{{ route('admin.accounting.accounts.show', 'nagad') }}">
      <span class="n {{ $nagad < 0 ? 'ad-red' : '' }}">৳ {{ number_format($nagad, 2) }}</span><span class="l">📲 Nagad</span>
    </a>
  </div>

  {{-- ===== Period ===== --}}
  <div class="ad-tabs">
    @foreach ($ranges as $key => $label)
      <a href="{{ route('admin.accounting.dashboard', ['range' => $key]) }}" class="{{ $range === $key ? 'on' : '' }}">{{ $label }}</a>
    @endforeach
  </div>

  <form class="ad-filter" method="GET" action="{{ route('admin.accounting.dashboard') }}">
    <input type="hidden" name="range" value="custom">
    <label>From <input type="date" name="from" value="{{ $range === 'custom' ? $from : '' }}"></label>
    <label>To <input type="date" name="to" value="{{ $range === 'custom' ? $to : '' }}"></label>
    <button type="submit" class="ad-btn ad-btn-gray">Show custom period</button>
  </form>

  <p class="ad-muted" style="margin:0 0 12px">Showing: <strong>{{ $title }}</strong> ({{ $span }})</p>

  <div class="ad-stats">
    <div class="ad-stat"><span class="n ad-green">৳ {{ number_format($periodIn, 2) }}</span><span class="l">Money in</span></div>
    <div class="ad-stat"><span class="n ad-red">৳ {{ number_format($periodOut, 2) }}</span><span class="l">Money out</span></div>
    <div class="ad-stat"><span class="n {{ $net < 0 ? 'ad-red' : 'ad-green' }}">{{ $net < 0 ? '−' : '' }}৳ {{ number_format(abs($net), 2) }}</span><span class="l">Net ({{ $net < 0 ? 'spent more than received' : 'received more than spent' }})</span></div>
  </div>

  {{-- ===== Every account ===== --}}
  <section class="ad-card">
    <div class="ad-card-head"><h2>Accounts at a glance</h2><a href="{{ route('admin.accounting.banks.index') }}">Manage banks →</a></div>
    <div class="ad-scroll">
      <table class="ad-table">
        <tr><th>Account</th><th class="r">Balance now</th><th class="r">Money in</th><th class="r">Money out</th></tr>
        @foreach ($rows as $r)
          <tr>
            <td>
              <a href="{{ $accUrl($r['account']) }}"><strong>{{ $accName($r['account']) }}</strong></a>
              @if ($r['account']->account_no) <span class="ad-muted">· A/C {{ $r['account']->account_no }}</span>@endif
            </td>
            <td class="r"><span class="{{ $r['balance'] < 0 ? 'ad-red' : '' }}">৳ {{ number_format($r['balance'], 2) }}</span></td>
            <td class="r">@if ($r['in'] > 0)<span class="ad-green">৳ {{ number_format($r['in'], 2) }}</span>@else — @endif</td>
            <td class="r">@if ($r['out'] > 0)<span class="ad-red">৳ {{ number_format($r['out'], 2) }}</span>@else — @endif</td>
          </tr>
        @endforeach
        <tr class="ad-tfoot">
          <td>Total</td>
          <td class="r">৳ {{ number_format($total, 2) }}</td>
          <td class="r">৳ {{ number_format($periodIn, 2) }}</td>
          <td class="r">৳ {{ number_format($periodOut, 2) }}</td>
        </tr>
      </table>
    </div>
    <p class="ad-hint">“Money in / out” follows the period chosen above. “Balance now” is the opening balance plus everything entered so far.</p>
  </section>

  <div class="ad-two">
    {{-- ===== Customers (shop orders) ===== --}}
    <section class="ad-card">
      <div class="ad-card-head"><h2>Customers &amp; shop payments</h2><a href="{{ route('admin.payments.index') }}">Payments →</a></div>
      @if ($shop === null)
        <p class="ad-muted">Shop payment data is not available.</p>
      @else
        <table class="ad-table">
          <tr>
            <td>Received from customers <span class="ad-muted">({{ $title }})</span></td>
            <td class="r"><span class="ad-green">৳ {{ number_format($shop['received']) }}</span></td>
          </tr>
          <tr>
            <td><a href="{{ route('admin.payments.index', ['tab' => 'due']) }}">Still to collect</a> <span class="ad-muted">({{ $shop['due_orders'] }} orders)</span></td>
            <td class="r"><span class="{{ $shop['due'] > 0 ? 'ad-red' : '' }}">৳ {{ number_format($shop['due']) }}</span></td>
          </tr>
          <tr>
            <td>To refund <span class="ad-muted">(cancelled / returned)</span></td>
            <td class="r"><span class="{{ $shop['refund_due'] > 0 ? 'ad-red' : '' }}">৳ {{ number_format($shop['refund_due']) }}</span></td>
          </tr>
        </table>
        <p class="ad-hint">These come from your orders and are shown for information. They are not added to the account balances above.</p>
      @endif
    </section>

    {{-- ===== Last 6 months ===== --}}
    <section class="ad-card">
      <div class="ad-card-head"><h2>Last 6 months</h2></div>
      @php $maxM = max(1, collect($months)->map(fn ($m) => max($m['in'], $m['out']))->max()); @endphp
      <div class="ad-scroll">
        <table class="ad-table">
          <tr><th>Month</th><th></th><th class="r">In</th><th class="r">Out</th><th class="r">Net</th></tr>
          @foreach ($months as $m)
            @php $mn = $m['in'] - $m['out']; @endphp
            <tr>
              <td>{{ $m['label'] }}</td>
              <td>
                <div class="ad-bar-bg"><span class="ad-bar-chart" style="width:{{ round($m['in'] / $maxM * 100) }}%;background:#16a34a"></span></div>
                <div class="ad-bar-bg" style="margin-top:3px"><span class="ad-bar-chart" style="width:{{ round($m['out'] / $maxM * 100) }}%;background:#c0392b"></span></div>
              </td>
              <td class="r">{{ number_format($m['in'], 2) }}</td>
              <td class="r">{{ number_format($m['out'], 2) }}</td>
              <td class="r"><span class="{{ $mn < 0 ? 'ad-red' : 'ad-green' }}">{{ $mn < 0 ? '−' : '' }}{{ number_format(abs($mn), 2) }}</span></td>
            </tr>
          @endforeach
        </table>
      </div>
      <p class="ad-hint">Green bar = money in, red bar = money out.</p>
    </section>
  </div>

  {{-- ===== Latest entries ===== --}}
  <section class="ad-card">
    <div class="ad-card-head"><h2>Latest entries (all accounts)</h2></div>
    @if ($recent->isEmpty())
      <p class="ad-muted">No entries yet. Open an account above and add the first one.</p>
    @else
      <div class="ad-scroll">
        <table class="ad-table">
          <tr><th>Date</th><th>Account</th><th>Note</th><th class="r">Money in</th><th class="r">Money out</th></tr>
          @foreach ($recent as $tx)
            <tr>
              <td>{{ $tx->txn_date->format('d M Y') }}</td>
              <td><a href="{{ $accUrl($tx->account) }}">{{ $accName($tx->account) }}</a></td>
              <td>{{ $tx->note ?: '—' }}</td>
              <td class="r">@if ($tx->isIn())<span class="ad-green">৳ {{ number_format((float) $tx->amount, 2) }}</span>@else — @endif</td>
              <td class="r">@if (!$tx->isIn())<span class="ad-red">৳ {{ number_format((float) $tx->amount, 2) }}</span>@else — @endif</td>
            </tr>
          @endforeach
        </table>
      </div>
    @endif
  </section>
@endsection
