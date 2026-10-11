@extends('admin.layout')

@section('title', 'Accounting Settings')

@section('content')
  <h1 class="ad-title">⚙️ Accounting Settings</h1>

  <section class="ad-card">
    <form class="ad-form" action="{{ route('admin.accounting.settings.update') }}" method="POST">
      @csrf
      @method('PUT')

      <h2>Payment Receive</h2>
      <div class="ad-row">
        <label>Default account
          <select name="default_account">
            <option value="">— None (choose every time) —</option>
            @foreach ($accounts as $a)
              <option value="{{ $a['id'] }}" {{ (string) old('default_account', $defaultAccount ?: '') === (string) $a['id'] ? 'selected' : '' }}>{{ $a['label'] }}</option>
            @endforeach
          </select>
        </label>
        <label>Courier charge per COD order (৳)
          <input type="number" name="courier_charge" value="{{ old('courier_charge', $courierCharge) }}" min="0" step="1">
        </label>
      </div>
      <p class="ad-hint">
        The default account is pre-selected when you add courier (COD), Rocket or Other payments to an account.
        The courier charge only fills in the "courier charge deducted" box for you (number of COD orders × this amount). You can still change it each time. Use 0 to leave it empty.
      </p>

      <h2 style="margin-top:18px">Which customer payments go to the accounts</h2>
      @if (!$ready)
        <p class="ad-muted">Please run the new migration first (<code>php artisan migrate</code>).</p>
      @else
        <div class="ad-row">
          <label>Count customer payments recorded from
            <input type="date" name="start_date" value="{{ old('start_date', $startDate) }}" max="{{ now()->toDateString() }}" required>
          </label>
        </div>
        <p class="ad-hint">
          Payments recorded before this date are <strong>not</strong> in any account, because your opening balances probably include that money already.
          @if ($olderCount > 0)
            Right now <strong>{{ $olderCount }}</strong> older payment(s) are left out.
          @endif
          If you move the date earlier, those older payments are not added by themselves: they appear in
          <a href="{{ route('admin.accounting.customers.receive') }}">Payment Receive</a> (waiting list: {{ $waitingCount }} now), where you choose the account and date. Only do this for money that is not part of your opening balance.
        </p>
      @endif

      <div class="ad-submit">
        <button type="submit" class="ad-btn">Save settings</button>
      </div>
    </form>
  </section>
@endsection
