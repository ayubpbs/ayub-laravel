@extends('admin.layout')

@section('title', 'Profit & Loss')

@section('content')
  <h1 class="ad-title">📊 Profit &amp; Loss</h1>

  @include('admin.accounting.reports._period')

  <div class="ad-stats">
    <div class="ad-stat"><span class="n ad-green">৳ {{ number_format($income, 2) }}</span><span class="l">Income</span></div>
    <div class="ad-stat"><span class="n ad-red">৳ {{ number_format($expense, 2) }}</span><span class="l">Expense</span></div>
    <div class="ad-stat ad-stat-hot"><span class="n {{ $profit < 0 ? 'ad-red' : 'ad-green' }}">{{ $profit < 0 ? '−' : '' }}৳ {{ number_format(abs($profit), 2) }}</span><span class="l">{{ $profit < 0 ? 'Loss' : 'Profit' }}</span></div>
  </div>

  <section class="ad-card">
    <div class="ad-card-head"><h2>Income</h2><a href="{{ route('admin.accounting.reports.income', ['range' => $range, 'from' => $from, 'to' => $to]) }}">Details →</a></div>
    @if (empty($incomeRows))
      <p class="ad-muted">No income in this period.</p>
    @else
      <table class="ad-table">
        @foreach ($incomeRows as $r)
          <tr><td>{{ $r['name'] }}</td><td class="r">৳ {{ number_format($r['amount'], 2) }}</td></tr>
        @endforeach
        <tr><th>Total income</th><th class="r">৳ {{ number_format($income, 2) }}</th></tr>
      </table>
    @endif
  </section>

  <section class="ad-card">
    <div class="ad-card-head"><h2>Expense</h2><a href="{{ route('admin.accounting.reports.expense', ['range' => $range, 'from' => $from, 'to' => $to]) }}">Details →</a></div>
    @if (empty($expenseRows))
      <p class="ad-muted">No expense in this period.</p>
    @else
      <table class="ad-table">
        @foreach ($expenseRows as $r)
          <tr><td>{{ $r['name'] }}</td><td class="r">৳ {{ number_format($r['amount'], 2) }}</td></tr>
        @endforeach
        <tr><th>Total expense</th><th class="r">৳ {{ number_format($expense, 2) }}</th></tr>
      </table>
    @endif
  </section>

  <section class="ad-card">
    <table class="ad-table">
      <tr><th>{{ $profit < 0 ? 'Loss' : 'Profit' }} (income − expense)</th><th class="r {{ $profit < 0 ? 'ad-red' : 'ad-green' }}">{{ $profit < 0 ? '−' : '' }}৳ {{ number_format(abs($profit), 2) }}</th></tr>
    </table>
  </section>

  @if ($manualIn > 0 || $manualOut > 0)
    <p class="ad-hint">
      Not counted above: ৳ {{ number_format($manualIn, 2) }} money in and ৳ {{ number_format($manualOut, 2) }} money out that were typed directly on an account page (not as Income / Expense).
      Add them as Income or Expense if they belong in the profit.
    </p>
  @endif
  <p class="ad-hint">This is a cash-basis report: it counts money actually received and paid, not unpaid customer dues or supplier bills. Transfers between your own accounts are left out.</p>
@endsection
