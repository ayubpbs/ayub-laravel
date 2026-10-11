@extends('admin.layout')

@section('title', $k['list'])

@section('content')
  <div class="ad-bar">
    <h1 class="ad-title">{{ $k['icon'] }} {{ $k['list'] }}</h1>
    <a class="ad-btn" href="{{ route('admin.accounting.' . $kind . '.create') }}">＋ {{ $k['add'] }}</a>
  </div>

  <div class="ad-stats">
    <div class="ad-stat"><span class="n {{ $kind === 'income' ? 'ad-green' : 'ad-red' }}">৳ {{ number_format($monthTotal, 2) }}</span><span class="l">This month</span></div>
    <div class="ad-stat"><span class="n">৳ {{ number_format($allTotal, 2) }}</span><span class="l">All time</span></div>
  </div>

  <section class="ad-card">
    <form class="ad-filter" method="GET" action="{{ route('admin.accounting.' . $kind . '.index') }}">
      <label>From <input type="date" name="from" value="{{ $f['from'] }}"></label>
      <label>To <input type="date" name="to" value="{{ $f['to'] }}"></label>
      <select name="account">
        <option value="">All accounts</option>
        @foreach ($accounts as $a)
          <option value="{{ $a['id'] }}" {{ (string) $f['account'] === (string) $a['id'] ? 'selected' : '' }}>{{ $a['label'] }}</option>
        @endforeach
      </select>
      <select name="category">
        <option value="">All categories</option>
        <option value="none" {{ $f['category'] === 'none' ? 'selected' : '' }}>No category</option>
        @foreach ($categories as $c)
          <option value="{{ $c->id }}" {{ $f['category'] === (string) $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
        @endforeach
      </select>
      <button type="submit" class="ad-btn ad-btn-gray">Filter</button>
      @if ($filtered)
        <a class="ad-btn ad-btn-gray" href="{{ route('admin.accounting.' . $kind . '.index') }}">Reset</a>
      @endif
    </form>

    @if ($entries->isEmpty())
      <p class="ad-muted">No {{ strtolower($k['label']) }} entries found.</p>
    @else
      <div class="ad-scroll">
        <table class="ad-table">
          <tr><th>Date</th><th>Category</th><th>Account</th><th>Note</th><th class="r">Amount</th><th></th></tr>
          @foreach ($entries as $e)
            <tr>
              <td>{{ $e->txn_date->format('d M Y') }}</td>
              <td>{{ $e->category->name ?? '—' }}</td>
              <td>{{ $e->account->name ?? '—' }}@if (($e->account->type ?? '') === 'bank' && $e->account->account_no) <span class="ad-muted">({{ $e->account->account_no }})</span>@endif</td>
              <td>{{ $e->note ?: '—' }}</td>
              <td class="r"><span class="{{ $kind === 'income' ? 'ad-green' : 'ad-red' }}">৳ {{ number_format((float) $e->amount, 2) }}</span></td>
              <td class="ad-actions">
                @if (empty($e->payment_id))
                  <a class="ad-link" href="{{ route('admin.accounting.' . $kind . '.edit', $e->id) }}">Edit</a>
                @else
                  <small class="ad-muted" title="Change it on the order's Payments page">Order payment</small>
                @endif
                <form action="{{ route('admin.accounting.' . $kind . '.destroy', $e->id) }}" method="POST" onsubmit="return confirm('Delete this entry? The account balance will change.')">
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
      <div class="ad-pager">{{ $entries->links('pagination::simple-default') }}</div>
    @endif
  </section>
@endsection
