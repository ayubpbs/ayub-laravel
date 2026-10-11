@extends('admin.layout')

@section('title', 'Money Transfer')

@section('content')
  <h1 class="ad-title">🔁 Money Transfer</h1>

  <div class="ad-stats">
    <div class="ad-stat"><span class="n">৳ {{ number_format($monthTotal, 2) }}</span><span class="l">Moved this month</span></div>
    <div class="ad-stat"><span class="n">৳ {{ number_format($allTotal, 2) }}</span><span class="l">Moved all time</span></div>
  </div>

  <section class="ad-card">
    <h2>New transfer</h2>
    <form class="ad-form" action="{{ route('admin.accounting.transfers.store') }}" method="POST">
      @csrf
      <div class="ad-row">
        <label>From (money leaves)
          <select name="from_account_id" required>
            <option value="">— Choose account —</option>
            @foreach ($accounts as $a)
              <option value="{{ $a['id'] }}" {{ (string) old('from_account_id') === (string) $a['id'] ? 'selected' : '' }}>{{ $a['label'] }}</option>
            @endforeach
          </select>
        </label>
        <label>To (money goes in)
          <select name="to_account_id" required>
            <option value="">— Choose account —</option>
            @foreach ($accounts as $a)
              <option value="{{ $a['id'] }}" {{ (string) old('to_account_id') === (string) $a['id'] ? 'selected' : '' }}>{{ $a['label'] }}</option>
            @endforeach
          </select>
        </label>
      </div>
      <div class="ad-row">
        <label>Amount (৳)
          <input type="number" name="amount" value="{{ old('amount') }}" step="0.01" min="0.01" required>
        </label>
        <label>Date
          <input type="date" name="txn_date" id="txnDate" value="{{ old('txn_date', now()->toDateString()) }}" required>
        </label>
        <label>Note <small>(optional)</small>
          <input type="text" name="note" value="{{ old('note') }}" maxlength="150" placeholder="e.g. Cash deposited to bank">
        </label>
      </div>
      <div class="ad-submit"><button type="submit" class="ad-btn">Transfer</button></div>
    </form>
    <p class="ad-hint">A transfer only moves money between your own accounts. It is not income or expense, so profit does not change.</p>
  </section>

  <section class="ad-card">
    <div class="ad-card-head"><h2>Transfers</h2></div>

    <form class="ad-filter" method="GET" action="{{ route('admin.accounting.transfers.index') }}">
      <label>From <input type="date" name="from" value="{{ $f['from'] }}"></label>
      <label>To <input type="date" name="to" value="{{ $f['to'] }}"></label>
      <select name="account">
        <option value="">All accounts</option>
        @foreach ($accounts as $a)
          <option value="{{ $a['id'] }}" {{ (string) $f['account'] === (string) $a['id'] ? 'selected' : '' }}>{{ $a['label'] }}</option>
        @endforeach
      </select>
      <button type="submit" class="ad-btn ad-btn-gray">Filter</button>
      @if ($filtered)
        <a class="ad-btn ad-btn-gray" href="{{ route('admin.accounting.transfers.index') }}">Reset</a>
      @endif
    </form>

    @if ($transfers->isEmpty())
      <p class="ad-muted">No transfers found.</p>
    @else
      <div class="ad-scroll">
        <table class="ad-table">
          <tr><th>Date</th><th>From</th><th>To</th><th>Note</th><th class="r">Amount</th><th></th></tr>
          @foreach ($transfers as $t)
            @php
              $in   = $incoming[$t->transfer_ref] ?? null;
              $note = trim((string) \Illuminate\Support\Str::after((string) $t->note, ' — '));
              $note = $note === trim((string) $t->note) ? '' : $note;
            @endphp
            <tr>
              <td>{{ $t->txn_date->format('d M Y') }}</td>
              <td>{{ $t->account->name ?? '—' }}</td>
              <td>{{ $in->account->name ?? '—' }}</td>
              <td>{{ $note !== '' ? $note : '—' }}</td>
              <td class="r">৳ {{ number_format((float) $t->amount, 2) }}</td>
              <td class="ad-actions">
                <a class="ad-link" href="{{ route('admin.accounting.transfers.edit', $t->transfer_ref) }}">Edit</a>
                <form action="{{ route('admin.accounting.transfers.destroy', $t->transfer_ref) }}" method="POST" onsubmit="return confirm('Delete this transfer? Both account balances will change back.')">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="ad-link ad-del">Delete</button>
                </form>
              </td>
            </tr>
          @endforeach
          @if ($filtered)
            <tr class="ad-tfoot">
              <td colspan="4">Total of this filtered list</td>
              <td class="r">৳ {{ number_format($listTotal, 2) }}</td>
              <td></td>
            </tr>
          @endif
        </table>
      </div>
      <div class="ad-pager">{{ $transfers->links('pagination::simple-default') }}</div>
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
