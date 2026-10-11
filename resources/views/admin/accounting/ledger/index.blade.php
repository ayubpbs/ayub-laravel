@extends('admin.layout')

@section('title', 'Transactions / Ledger')

@section('content')
  @php
    $accUrl = function ($a) {
        if (!$a) return null;
        return $a->type === 'bank'
            ? route('admin.accounting.banks.show', $a->id)
            : route('admin.accounting.accounts.show', $a->type);
    };
    $net = $totalIn - $totalOut;
  @endphp

  <div class="ad-bar">
    <h1 class="ad-title">📒 Transactions / Ledger</h1>
    <div>
      <a class="ad-btn" href="{{ route('admin.accounting.income.create') }}">＋ Income</a>
      <a class="ad-btn" href="{{ route('admin.accounting.expense.create') }}">＋ Expense</a>
      <a class="ad-btn ad-btn-gray" href="{{ route('admin.accounting.transfers.index') }}">Transfer</a>
    </div>
  </div>

  <div class="ad-stats">
    <div class="ad-stat"><span class="n ad-green">৳ {{ number_format($totalIn, 2) }}</span><span class="l">Money in {{ $filtered ? '(filtered list)' : '(all entries)' }}</span></div>
    <div class="ad-stat"><span class="n ad-red">৳ {{ number_format($totalOut, 2) }}</span><span class="l">Money out {{ $filtered ? '(filtered list)' : '(all entries)' }}</span></div>
    <div class="ad-stat"><span class="n {{ $net < 0 ? 'ad-red' : '' }}">৳ {{ number_format($net, 2) }}</span><span class="l">Difference</span></div>
    <div class="ad-stat"><span class="n">{{ $transactions->total() }}</span><span class="l">Entries</span></div>
  </div>

  <section class="ad-card">
    <form class="ad-filter" method="GET" action="{{ route('admin.accounting.ledger.index') }}">
      <label>From <input type="date" name="from" value="{{ $f['from'] }}"></label>
      <label>To <input type="date" name="to" value="{{ $f['to'] }}"></label>
      <select name="account">
        <option value="">All accounts</option>
        @foreach ($accounts as $a)
          <option value="{{ $a['id'] }}" {{ (string) $f['account'] === (string) $a['id'] ? 'selected' : '' }}>{{ $a['label'] }}</option>
        @endforeach
      </select>
      <select name="dir">
        <option value="">Money in &amp; out</option>
        <option value="in"  {{ $f['dir'] === 'in'  ? 'selected' : '' }}>Money in only</option>
        <option value="out" {{ $f['dir'] === 'out' ? 'selected' : '' }}>Money out only</option>
      </select>
      <select name="type">
        <option value="">All types</option>
        @foreach ($sources as $key => $label)
          <option value="{{ $key }}" {{ $f['type'] === $key ? 'selected' : '' }}>{{ $label }}</option>
        @endforeach
      </select>
      <select name="category">
        <option value="">All categories</option>
        <option value="none" {{ $f['category'] === 'none' ? 'selected' : '' }}>No category</option>
        @foreach (['income' => 'Income', 'expense' => 'Expense'] as $ctype => $clabel)
          @if (!empty($categories[$ctype]) && $categories[$ctype]->isNotEmpty())
            <optgroup label="{{ $clabel }} categories">
              @foreach ($categories[$ctype] as $c)
                <option value="{{ $c->id }}" {{ $f['category'] === (string) $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
              @endforeach
            </optgroup>
          @endif
        @endforeach
      </select>
      <input type="text" name="q" value="{{ $f['q'] }}" maxlength="100" placeholder="Search in note…">
      <button type="submit" class="ad-btn ad-btn-gray">Filter</button>
      @if ($filtered)
        <a class="ad-btn ad-btn-gray" href="{{ route('admin.accounting.ledger.index') }}">Reset</a>
      @endif
    </form>

    @if ($transactions->isEmpty())
      <p class="ad-muted">No entries found.</p>
    @else
      <div class="ad-scroll">
        <table class="ad-table">
          <tr>
            <th>Date</th><th>Account</th><th>Type</th><th>Category</th><th>Note</th>
            <th class="r">Money in</th><th class="r">Money out</th>
            @if ($running !== null)<th class="r">Balance after</th>@endif
          </tr>
          @foreach ($transactions as $tx)
            <tr>
              <td>{{ $tx->txn_date->format('d M Y') }}</td>
              <td>
                @if ($accUrl($tx->account))
                  <a href="{{ $accUrl($tx->account) }}">{{ $tx->account->name }}</a>
                @else
                  —
                @endif
              </td>
              <td>{{ $sources[$tx->source] ?? ucfirst($tx->source) }}</td>
              <td>{{ $tx->category->name ?? '—' }}</td>
              <td>{{ $tx->note ?: '—' }}</td>
              <td class="r">@if ($tx->isIn())<span class="ad-green">৳ {{ number_format((float) $tx->amount, 2) }}</span>@else — @endif</td>
              <td class="r">@if (!$tx->isIn())<span class="ad-red">৳ {{ number_format((float) $tx->amount, 2) }}</span>@else — @endif</td>
              @if ($running !== null)
                <td class="r"><span class="{{ $running[$tx->id] < 0 ? 'ad-red' : '' }}">৳ {{ number_format($running[$tx->id], 2) }}</span></td>
              @endif
            </tr>
          @endforeach
        </table>
      </div>
      <div class="ad-pager">{{ $transactions->links('pagination::simple-default') }}</div>
      <p class="ad-hint">This page only shows entries. Add or delete them from Income, Expense, Money Transfer, or the account's own page. A transfer appears twice: money out of one account and money into the other.@if ($running !== null) "Balance after" includes the account's opening balance.@endif</p>
    @endif
  </section>
@endsection
