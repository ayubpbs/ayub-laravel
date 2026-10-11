@extends('admin.layout')

@section('title', $label . ' Report')

@section('content')
  @php
    $isIncome = $kind === 'income';
    $ledger   = fn (array $extra = []) => route('admin.accounting.ledger.index', array_filter(['type' => $kind, 'from' => $from, 'to' => $to] + $extra, fn ($v) => $v !== null && $v !== ''));
  @endphp

  <h1 class="ad-title">{{ $isIncome ? '📥' : '📤' }} {{ $label }} Report</h1>

  @include('admin.accounting.reports._period')

  <div class="ad-stats">
    <div class="ad-stat ad-stat-hot"><span class="n {{ $isIncome ? 'ad-green' : 'ad-red' }}">৳ {{ number_format($total, 2) }}</span><span class="l">Total {{ strtolower($label) }}</span></div>
    <div class="ad-stat"><span class="n">{{ $count }}</span><span class="l">Entries</span></div>
    <div class="ad-stat"><span class="n">৳ {{ number_format($average, 2) }}</span><span class="l">Average per entry</span></div>
  </div>

  @if ($count === 0)
    <section class="ad-card"><p class="ad-muted">No {{ strtolower($label) }} entries in this period.</p></section>
  @else
    <section class="ad-card">
      <div class="ad-card-head"><h2>By category</h2><a href="{{ $ledger() }}">All entries →</a></div>
      <div class="ad-scroll">
        <table class="ad-table">
          <tr><th>Category</th><th class="r">Entries</th><th class="r">Amount</th><th class="r">Share</th></tr>
          @foreach ($byCategory as $r)
            <tr>
              <td><a href="{{ $ledger(['category' => $r['id'] ?: 'none']) }}">{{ $r['name'] }}</a></td>
              <td class="r">{{ $r['count'] }}</td>
              <td class="r"><strong>৳ {{ number_format($r['amount'], 2) }}</strong></td>
              <td class="r">{{ $r['percent'] }}%</td>
            </tr>
          @endforeach
        </table>
      </div>
    </section>

    <section class="ad-card">
      <h2>By account</h2>
      <div class="ad-scroll">
        <table class="ad-table">
          <tr><th>Account</th><th class="r">Entries</th><th class="r">Amount</th><th class="r">Share</th></tr>
          @foreach ($byAccount as $r)
            <tr>
              <td>{{ $r['name'] }}</td>
              <td class="r">{{ $r['count'] }}</td>
              <td class="r"><strong>৳ {{ number_format($r['amount'], 2) }}</strong></td>
              <td class="r">{{ $r['percent'] }}%</td>
            </tr>
          @endforeach
        </table>
      </div>
    </section>

    <section class="ad-card">
      <h2>Biggest entries</h2>
      <div class="ad-scroll">
        <table class="ad-table">
          <tr><th>Date</th><th>Account</th><th>Category</th><th>Note</th><th class="r">Amount</th></tr>
          @foreach ($biggest as $t)
            <tr>
              <td>{{ $t->txn_date->format('d M Y') }}</td>
              <td>{{ optional($t->account)->name }}</td>
              <td>{{ optional($t->category)->name ?: '—' }}</td>
              <td>{{ $t->note ?: '—' }}</td>
              <td class="r"><strong>৳ {{ number_format($t->amount, 2) }}</strong></td>
            </tr>
          @endforeach
        </table>
      </div>
    </section>
  @endif

  <p class="ad-hint">Only entries added as {{ strtolower($label) }} (including customer {{ $isIncome ? 'payments' : 'refunds' }}) are counted. Money moved between your own accounts is not {{ strtolower($label) }}.</p>
@endsection
