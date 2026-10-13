@extends('admin.layout')

@section('title', $item->title)

@section('content')
  @php $asset = $k['side'] === 'asset'; @endphp
  <p class="ad-crumbs"><a href="{{ route('admin.accounting.savings.index', ['kind' => $item->kind]) }}">← {{ $k['label'] }}</a></p>
  <h1 class="ad-title">{{ $k['icon'] }} {{ $item->title }}</h1>
  <p class="ad-muted" style="margin:-6px 0 12px">
    {{ $k['label'] }} ({{ $k['bn'] }})@if ($item->party) · {{ $item->party }}@endif @if ($item->reference_no) · No. {{ $item->reference_no }}@endif
  </p>

  <div class="ad-stats">
    <div class="ad-stat"><span class="n">৳ {{ number_format((float) $item->principal, 2) }}</span><span class="l">Amount</span></div>
    <div class="ad-stat"><span class="n">৳ {{ number_format($settled, 2) }}</span><span class="l">{{ $asset ? 'Received back' : 'Repaid' }}</span></div>
    <div class="ad-stat ad-stat-hot"><span class="n {{ !$asset && $outstanding > 0 ? 'ad-red' : '' }}">৳ {{ number_format($outstanding, 2) }}</span><span class="l">{{ $outstanding > 0 ? 'Outstanding' : 'Finished' }}</span></div>
  </div>

  <section class="ad-card">
    <div class="ad-card-head"><h2>Details</h2>
      <span class="ad-actions">
        <a class="ad-link" href="{{ route('admin.accounting.savings.edit', $item->id) }}">Edit</a>
        <form action="{{ route('admin.accounting.savings.destroy', $item->id) }}" method="POST" onsubmit="return confirm('Delete this item and its entries? Account entries made from it are removed too.')">
          @csrf @method('DELETE')
          <button type="submit" class="ad-link ad-del">Delete</button>
        </form>
      </span>
    </div>
    <table class="ad-table">
      <tr><td>Start date</td><td class="r">{{ $item->start_date->format('d M Y') }}</td></tr>
      <tr><td>Due / maturity date</td><td class="r">{{ $item->maturity_date ? $item->maturity_date->format('d M Y') : '—' }}</td></tr>
      <tr><td>Interest rate</td><td class="r">{{ $item->interest_rate !== null ? $item->interest_rate . ' %' : '—' }}</td></tr>
      <tr><td>Note</td><td class="r">{{ $item->note ?: '—' }}</td></tr>
    </table>
  </section>

  @if ($outstanding > 0)
    <section class="ad-card">
      <h2>{{ $k['settle'] }}</h2>
      <form class="ad-form" action="{{ route('admin.accounting.savings.settle', $item->id) }}" method="POST">
        @csrf
        <div class="ad-row">
          <label>{{ $asset ? 'Money went into' : 'Money paid from' }}
            <select name="account_id" required>
              <option value="">— Choose account —</option>
              @foreach ($accounts as $a)
                <option value="{{ $a['id'] }}" {{ (string) old('account_id') === (string) $a['id'] ? 'selected' : '' }}>{{ $a['label'] }}</option>
              @endforeach
            </select>
          </label>
          <label>Amount (৳)
            <input type="number" name="amount" value="{{ old('amount', number_format($outstanding, 2, '.', '')) }}" step="0.01" min="0.01" max="{{ number_format($outstanding, 2, '.', '') }}" required>
          </label>
          <label>Date
            <input type="date" name="paid_date" id="paidDate" value="{{ old('paid_date', now()->toDateString()) }}" required>
          </label>
        </div>
        <label>Note <small>(optional)</small>
          <input type="text" name="note" value="{{ old('note') }}" maxlength="255">
        </label>
        <div class="ad-submit"><button type="submit" class="ad-btn">Save</button></div>
      </form>
      <p class="ad-hint">You can enter a part of the amount now and the rest later. This also {{ $asset ? 'adds the money to' : 'takes the money from' }} the chosen account.
        @if ($asset && in_array($item->kind, ['sanchaypatra', 'fdr'])) Enter the invested amount here; record the interest you received as a normal Income entry.@endif
        @if ($asset && $item->kind === 'loan_given') Enter the loan amount here; if you also received interest, record it as a normal Income entry.@endif
        @if (!$asset) Enter only the loan amount here; record the interest you paid as a normal Expense entry.@endif
      </p>
    </section>
  @endif

  <section class="ad-card">
    <div class="ad-card-head"><h2>{{ $asset ? 'Received so far' : 'Repayments so far' }}</h2></div>
    @if ($payments->isEmpty())
      <p class="ad-muted">Nothing yet.</p>
    @else
      <div class="ad-scroll">
        <table class="ad-table">
          <tr><th>Date</th><th>Note</th><th class="r">Amount</th><th></th></tr>
          @foreach ($payments as $p)
            <tr>
              <td>{{ $p->paid_date->format('d M Y') }}</td>
              <td>{{ $p->note ?: '—' }}</td>
              <td class="r">৳ {{ number_format((float) $p->amount, 2) }}</td>
              <td class="ad-actions">
                <form action="{{ route('admin.accounting.savings.unsettle', [$item->id, $p->id]) }}" method="POST" onsubmit="return confirm('Delete this entry? The account balance is corrected too.')">
                  @csrf @method('DELETE')
                  <button type="submit" class="ad-link ad-del">Delete</button>
                </form>
              </td>
            </tr>
          @endforeach
        </table>
      </div>
    @endif
  </section>

  <script>
    (function () {
      var d = document.getElementById('paidDate');
      if (d && !{{ old('paid_date') ? 'true' : 'false' }}) {
        d.value = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);
      }
    })();
  </script>
@endsection
