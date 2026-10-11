@extends('admin.layout')

@php $editing = !empty($entry); $heading = $editing ? 'Edit ' . strtolower($k['label']) : $k['add']; @endphp
@section('title', $heading)

@section('content')
  <p class="ad-crumbs"><a href="{{ route('admin.accounting.' . $kind . '.index') }}">← {{ $k['list'] }}</a></p>
  <h1 class="ad-title">{{ $k['icon'] }} {{ $heading }}</h1>

  <section class="ad-card">
    <form class="ad-form" action="{{ $editing ? route('admin.accounting.' . $kind . '.update', $entry->id) : route('admin.accounting.' . $kind . '.store') }}" method="POST">
      @csrf
      @if ($editing) @method('PUT') @endif
      <div class="ad-row">
        <label>{{ $kind === 'income' ? 'Money goes into' : 'Money is paid from' }}
          <select name="account_id" required>
            <option value="">— Choose account —</option>
            @foreach ($accounts as $a)
              <option value="{{ $a['id'] }}" {{ (string) old('account_id', $editing ? $entry->account_id : '') === (string) $a['id'] ? 'selected' : '' }}>{{ $a['label'] }}</option>
            @endforeach
          </select>
        </label>
        <label>Category <small>(optional)</small>
          <select name="category_id">
            <option value="">— No category —</option>
            @foreach ($categories as $c)
              <option value="{{ $c->id }}" {{ (string) old('category_id', $editing ? $entry->category_id : request('category')) === (string) $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
            @endforeach
          </select>
        </label>
      </div>
      <div class="ad-row">
        <label>Amount (৳)
          <input type="number" name="amount" value="{{ old('amount', $editing ? $entry->amount : '') }}" step="0.01" min="0.01" required>
        </label>
        <label>Date
          <input type="date" name="txn_date" id="txnDate" value="{{ old('txn_date', $editing ? $entry->txn_date->toDateString() : now()->toDateString()) }}" required>
        </label>
      </div>
      <label>Note <small>(optional)</small>
        <input type="text" name="note" value="{{ old('note', $editing ? $entry->note : '') }}" maxlength="255" placeholder="{{ $kind === 'income' ? 'e.g. Service charge from customer…' : 'e.g. Shop rent for this month…' }}">
      </label>
      <div class="ad-submit">
        <button type="submit" class="ad-btn">{{ $editing ? 'Save changes' : 'Save ' . strtolower($k['label']) }}</button>
        <a class="ad-btn ad-btn-gray" href="{{ route('admin.accounting.' . $kind . '.index') }}">Cancel</a>
      </div>
    </form>
    @if ($categories->isEmpty())
      <p class="ad-hint">No {{ strtolower($k['label']) }} category yet. You can <a href="{{ route('admin.accounting.categories.' . $kind) }}">add categories here</a>.</p>
    @endif
    <p class="ad-hint">This entry is also shown on the chosen account's own page and changes its balance.{{ $editing ? ' Saving changes updates the balances of the old and the new account.' : '' }}</p>
  </section>

  <script>
    // Default the date to the browser's local day (the server clock may be in another time zone)
    (function () {
      var d = document.getElementById('txnDate');
      if (d && !{{ ($editing || old('txn_date')) ? 'true' : 'false' }}) {
        d.value = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 10);
      }
    })();
  </script>
@endsection
