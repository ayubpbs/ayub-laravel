@extends('admin.layout')

@section('title', 'Cash Flow')

@section('content')
  @php $m = fn ($v) => ($v < 0 ? '−' : '') . '৳ ' . number_format(abs($v), 2); @endphp

  <h1 class="ad-title">🌊 Cash Flow</h1>

  @include('admin.accounting.reports._period')

  <div class="ad-stats">
    <div class="ad-stat"><span class="n">{{ $m($open) }}</span><span class="l">Balance at start</span></div>
    <div class="ad-stat"><span class="n ad-green">{{ $m($in) }}</span><span class="l">Money in</span></div>
    <div class="ad-stat"><span class="n ad-red">{{ $m($out) }}</span><span class="l">Money out</span></div>
    <div class="ad-stat ad-stat-hot"><span class="n {{ $close < 0 ? 'ad-red' : '' }}">{{ $m($close) }}</span><span class="l">Balance at end</span></div>
  </div>

  <section class="ad-card">
    <h2>Every account</h2>
    <div class="ad-scroll">
      <table class="ad-table">
        <tr><th>Account</th><th class="r">Start</th><th class="r">In</th><th class="r">Out</th><th class="r">Transfer in</th><th class="r">Transfer out</th><th class="r">End</th></tr>
        @foreach ($rows as $r)
          <tr>
            <td>{{ $r['account']->type === 'bank' ? '🏦' : (\App\Models\Account::types()[$r['account']->type][1] ?? '') }} {{ $r['account']->name }}</td>
            <td class="r">{{ $m($r['open']) }}</td>
            <td class="r">{{ $m($r['in']) }}</td>
            <td class="r">{{ $m($r['out']) }}</td>
            <td class="r">{{ $m($r['tIn']) }}</td>
            <td class="r">{{ $m($r['tOut']) }}</td>
            <td class="r"><strong>{{ $m($r['close']) }}</strong></td>
          </tr>
        @endforeach
        <tr><th>Total</th><th class="r">{{ $m($open) }}</th><th class="r">{{ $m($in) }}</th><th class="r">{{ $m($out) }}</th><th class="r" colspan="2">cancel out</th><th class="r">{{ $m($close) }}</th></tr>
      </table>
    </div>
    <p class="ad-hint">Transfers only move money between your own accounts, so they are not part of "Money in / out" and add up to zero in the total.</p>
  </section>

  <section class="ad-card">
    <h2>Month by month</h2>
    @if (empty($months))
      <p class="ad-muted">No money moved in this period.</p>
    @else
      <div class="ad-scroll">
        <table class="ad-table">
          <tr><th>Month</th><th class="r">Money in</th><th class="r">Money out</th><th class="r">Net</th></tr>
          @foreach ($months as $mo)
            @php $net = $mo['in'] - $mo['out']; @endphp
            <tr>
              <td>{{ $mo['label'] }}</td>
              <td class="r">{{ $m($mo['in']) }}</td>
              <td class="r">{{ $m($mo['out']) }}</td>
              <td class="r {{ $net < 0 ? 'ad-red' : 'ad-green' }}"><strong>{{ $m($net) }}</strong></td>
            </tr>
          @endforeach
        </table>
      </div>
    @endif
  </section>
@endsection
