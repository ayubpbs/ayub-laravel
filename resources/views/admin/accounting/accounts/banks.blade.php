@extends('admin.layout')

@section('title', 'Bank Accounts')

@section('content')
  <h1 class="ad-title">🏦 Bank Accounts</h1>

  @include('admin.accounting.accounts._tabs', ['active' => 'bank'])

  <div class="ad-stats">
    <div class="ad-stat"><span class="n {{ $total < 0 ? 'ad-red' : '' }}">৳ {{ number_format($total, 2) }}</span><span class="l">Total in all banks</span></div>
    <div class="ad-stat"><span class="n">{{ $rows->count() }}</span><span class="l">Bank accounts</span></div>
  </div>

  <section class="ad-card">
    <div class="ad-card-head"><h2>Your bank accounts</h2></div>
    @if ($rows->isEmpty())
      <p class="ad-muted">No bank account yet. Add your first one below.</p>
    @else
      <div class="ad-scroll">
        <table class="ad-table">
          <tr><th>Bank</th><th>Account no.</th><th class="r">Balance</th><th></th></tr>
          @foreach ($rows as $r)
            <tr>
              <td><a href="{{ route('admin.accounting.banks.show', $r['account']->id) }}"><strong>{{ $r['account']->name }}</strong></a></td>
              <td>{{ $r['account']->account_no ?: '—' }}</td>
              <td class="r"><span class="{{ $r['balance'] < 0 ? 'ad-red' : '' }}">৳ {{ number_format($r['balance'], 2) }}</span></td>
              <td class="ad-actions">
                <a class="ad-link" href="{{ route('admin.accounting.banks.show', $r['account']->id) }}">Open</a>
                <a class="ad-link" href="{{ route('admin.accounting.banks.index', ['edit' => $r['account']->id]) }}#bankForm">Edit</a>
                <form action="{{ route('admin.accounting.banks.destroy', $r['account']->id) }}" method="POST" onsubmit="return confirm('Delete this bank account?')">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="ad-link ad-del">Delete</button>
                </form>
              </td>
            </tr>
          @endforeach
        </table>
      </div>
      <p class="ad-hint">A bank account with entries cannot be deleted. Delete its entries first.</p>
    @endif
  </section>

  <section class="ad-card" id="bankForm">
    @if ($editing)
      <h2>Edit bank account</h2>
      <form class="ad-form" action="{{ route('admin.accounting.banks.update', $editing->id) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="ad-row">
          <label>Bank name
            <input type="text" name="name" value="{{ old('name', $editing->name) }}" maxlength="60" required>
          </label>
          <label>Account number <small>(optional)</small>
            <input type="text" name="account_no" value="{{ old('account_no', $editing->account_no) }}" maxlength="40">
          </label>
        </div>
        <div class="ad-submit">
          <button type="submit" class="ad-btn">Save changes</button>
          <a class="ad-btn ad-btn-gray" href="{{ route('admin.accounting.banks.index') }}">Cancel</a>
        </div>
      </form>
      <p class="ad-hint">The opening balance is changed on the account's own page.</p>
    @else
      <h2>Add bank account</h2>
      <form class="ad-form" action="{{ route('admin.accounting.banks.store') }}" method="POST">
        @csrf
        <div class="ad-row">
          <label>Bank name
            <input type="text" name="name" value="{{ old('name') }}" maxlength="60" placeholder="e.g. Dutch-Bangla Bank, City Bank" required>
          </label>
          <label>Account number <small>(optional)</small>
            <input type="text" name="account_no" value="{{ old('account_no') }}" maxlength="40">
          </label>
          <label>Opening balance (৳)
            <input type="number" name="opening_balance" value="{{ old('opening_balance', 0) }}" step="0.01" min="0">
          </label>
        </div>
        <div class="ad-submit"><button type="submit" class="ad-btn">Add bank account</button></div>
      </form>
    @endif
  </section>
@endsection
