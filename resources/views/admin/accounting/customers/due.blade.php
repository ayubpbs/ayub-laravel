@extends('admin.layout')

@section('title', 'Customer Due')

@section('content')
  <div class="ad-bar">
    <h1 class="ad-title">🧾 Customer Due</h1>
    <a class="ad-btn" href="{{ route('admin.accounting.customers.receive') }}">Payment Receive</a>
  </div>

  <div class="ad-stats">
    <div class="ad-stat {{ $totalDue > 0 ? 'ad-stat-hot' : '' }}"><span class="n">৳ {{ number_format($totalDue) }}</span><span class="l">Customers still owe</span></div>
    <div class="ad-stat"><span class="n">{{ $customers->count() }}</span><span class="l">Customers with due</span></div>
    <div class="ad-stat"><span class="n">{{ $orderCount }}</span><span class="l">Orders with due</span></div>
    <a class="ad-stat" href="{{ route('admin.accounting.customers.receive') }}">
      <span class="n">৳ {{ number_format($withCourier) }}</span><span class="l">With courier, not received yet ({{ $withCourierCount }})</span>
    </a>
  </div>

  <form class="ad-filter" method="GET" action="{{ route('admin.accounting.customers.due') }}">
    <input type="text" name="q" value="{{ $q }}" placeholder="Name or mobile…">
    <button type="submit" class="ad-btn ad-btn-gray">Search</button>
    @if ($q !== '')<a class="ad-btn ad-btn-gray" href="{{ route('admin.accounting.customers.due') }}">Clear</a>@endif
  </form>

  <section class="ad-card">
    @if ($customers->isEmpty())
      <p class="ad-muted">{{ $q !== '' ? 'No customer found.' : 'Nothing is due. Every live order is fully paid.' }}</p>
    @else
      <div class="ad-scroll">
        <table class="ad-table">
          <tr><th>Customer</th><th>Orders with due</th><th>Oldest order</th><th class="r">Due</th><th></th></tr>
          @foreach ($customers as $c)
            <tr>
              <td><strong>{{ $c['name'] }}</strong><br><small class="ad-muted">{{ $c['phone'] }}</small></td>
              <td>
                @foreach ($c['orders'] as $o)
                  <a href="{{ route('admin.orders.show', $o->id) }}">#{{ $o->id }}</a> <small class="ad-muted">(৳ {{ number_format($o->dueAmount()) }})</small>@unless ($loop->last), @endunless
                @endforeach
              </td>
              <td>{{ $c['oldest']->format('d M Y') }}<br><small class="ad-muted">{{ $c['oldest']->diffForHumans() }}</small></td>
              <td class="r ad-red"><strong>৳ {{ number_format($c['due']) }}</strong></td>
              <td class="ad-actions">
                <a class="ad-link" href="{{ route('admin.payments.create', ['order' => $c['orders']->first()->id]) }}">Record payment</a>
              </td>
            </tr>
          @endforeach
        </table>
      </div>
      <p class="ad-hint">Customers are grouped by mobile number. Cancelled and returned orders are not counted. Due = order total − payments received + refunds.</p>
    @endif
  </section>
@endsection
