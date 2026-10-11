@extends('admin.layout')

@section('title', 'Courier Returns')

@section('content')
  <h1 class="ad-title">Returns</h1>

  <section class="ad-card">
    <h2>Cancelled by courier — parcel coming back</h2>
    @if ($attention->isEmpty())
      <p class="ad-muted">No parcels need attention. Parcels that Steadfast reports as cancelled appear here (use Tracking → Refresh to update statuses).</p>
    @else
      <div class="ad-scroll">
        <table class="ad-table">
          <tr><th>Order</th><th>Customer</th><th>Courier status</th><th>Return request</th><th></th></tr>
          @foreach ($attention as $o)
            <tr>
              <td><a href="{{ route('admin.orders.show', $o->id) }}"><strong>#{{ $o->id }}</strong></a><br><small class="ad-muted">৳ {{ number_format($o->total) }}</small></td>
              <td>{{ $o->name }}<br><small class="ad-muted">{{ $o->phone }}</small></td>
              <td><span class="ad-st {{ \App\Support\Steadfast::badge($o->courier_status) }}">{{ \App\Support\Steadfast::label($o->courier_status) }}</span></td>
              <td>{{ $o->return_requested_at ? 'Requested ' . $o->return_requested_at->format('d M') : 'Not requested' }}</td>
              <td class="ad-actions">
                @unless ($o->return_requested_at)
                  <form action="{{ route('admin.courier.returns.request', $o->id) }}" method="POST">
                    @csrf
                    <button type="submit" class="ad-link">Request return</button>
                  </form>
                @endunless
                <form action="{{ route('admin.courier.returns.mark', $o->id) }}" method="POST"
                      onsubmit="return confirm('Mark order #{{ $o->id }} as Returned? The products go back into stock and the status cannot be changed afterwards.');">
                  @csrf
                  <button type="submit" class="ad-link">Mark as Returned</button>
                </form>
              </td>
            </tr>
          @endforeach
        </table>
      </div>
      <p class="ad-hint">Press "Mark as Returned" only when the parcel has physically reached your shop. It restocks the products.</p>
    @endif
  </section>

  <section class="ad-card">
    <div class="ad-card-head"><h2>Return requests at Steadfast</h2></div>
    @if ($remote === null)
      <p class="ad-muted">Connect your Steadfast API keys (Courier → Steadfast) to see return requests here.</p>
    @elseif (!$remote['ok'])
      <p class="ad-muted">Could not load return requests: {{ $remote['message'] }}</p>
    @else
      @php
        $rows = $remote['data'];
        $rows = $rows['data'] ?? $rows['return_requests'] ?? (array_values($rows) === $rows ? $rows : []);
        $rows = array_filter((array) $rows, 'is_array');
      @endphp
      @if (!count($rows))
        <p class="ad-muted">No return requests.</p>
      @else
        <div class="ad-scroll">
          <table class="ad-table">
            <tr><th>ID</th><th>Consignment</th><th>Reason</th><th>Status</th><th>Created</th></tr>
            @foreach ($rows as $r)
              <tr>
                <td>{{ $r['id'] ?? '—' }}</td>
                <td>{{ $r['consignment_id'] ?? '—' }}</td>
                <td>{{ $r['reason'] ?? '—' }}</td>
                <td>{{ $r['status'] ?? '—' }}</td>
                <td>{{ $r['created_at'] ?? '—' }}</td>
              </tr>
            @endforeach
          </table>
        </div>
      @endif
    @endif
  </section>

  <section class="ad-card">
    <h2>Returned orders</h2>
    @if ($returned->isEmpty())
      <p class="ad-muted">No returned orders yet.</p>
    @else
      <div class="ad-scroll">
        <table class="ad-table">
          <tr><th>Order</th><th>Customer</th><th>Total</th><th>Date</th></tr>
          @foreach ($returned as $o)
            <tr>
              <td><a href="{{ route('admin.orders.show', $o->id) }}"><strong>#{{ $o->id }}</strong></a></td>
              <td>{{ $o->name }}</td>
              <td>৳ {{ number_format($o->total) }}</td>
              <td>{{ $o->updated_at->format('d M Y') }}</td>
            </tr>
          @endforeach
        </table>
      </div>
    @endif
  </section>
@endsection
