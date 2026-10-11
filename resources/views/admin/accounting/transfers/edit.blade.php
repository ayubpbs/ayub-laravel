@extends('admin.layout')

@section('title', 'Edit Transfer')

@section('content')
  <p class="ad-crumbs"><a href="{{ route('admin.accounting.transfers.index') }}">← Money Transfer</a></p>
  <h1 class="ad-title">🔁 Edit Transfer</h1>

  <section class="ad-card">
    <form class="ad-form" action="{{ route('admin.accounting.transfers.update', $ref) }}" method="POST">
      @csrf
      @method('PUT')
      <div class="ad-row">
        <label>From (money leaves)
          <select name="from_account_id" required>
            @foreach ($accounts as $a)
              <option value="{{ $a['id'] }}" {{ (string) old('from_account_id', $transfer->account_id) === (string) $a['id'] ? 'selected' : '' }}>{{ $a['label'] }}</option>
            @endforeach
          </select>
        </label>
        <label>To (money goes in)
          <select name="to_account_id" required>
            @foreach ($accounts as $a)
              <option value="{{ $a['id'] }}" {{ (string) old('to_account_id', $toId) === (string) $a['id'] ? 'selected' : '' }}>{{ $a['label'] }}</option>
            @endforeach
          </select>
        </label>
      </div>
      <div class="ad-row">
        <label>Amount (৳)
          <input type="number" name="amount" value="{{ old('amount', $transfer->amount) }}" step="0.01" min="0.01" required>
        </label>
        <label>Date
          <input type="date" name="txn_date" value="{{ old('txn_date', $transfer->txn_date->toDateString()) }}" required>
        </label>
        <label>Note <small>(optional)</small>
          <input type="text" name="note" value="{{ old('note', $note) }}" maxlength="150">
        </label>
      </div>
      <div class="ad-submit">
        <button type="submit" class="ad-btn">Save changes</button>
        <a class="ad-btn ad-btn-gray" href="{{ route('admin.accounting.transfers.index') }}">Cancel</a>
      </div>
    </form>
    <p class="ad-hint">Both sides of the transfer are changed together, so the two account balances always stay in step.</p>
  </section>
@endsection
