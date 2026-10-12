@extends('admin.layout')

@section('title', 'Steadfast')

@section('content')
  <h1 class="ad-title">Steadfast Courier</h1>

  @if (!$configured)
    <section class="ad-card">
      <h2>Connect your Steadfast account</h2>
      <p>Open the Steadfast merchant panel, go to the API settings, and copy your API key and secret key. Then add these two lines to the <code>.env</code> file in your project and reload this page:</p>
<pre>STEADFAST_API_KEY=your_api_key
STEADFAST_SECRET_KEY=your_secret_key</pre>
      <p class="ad-hint">If the site is cached, run <code>php artisan config:clear</code> after editing <code>.env</code>.</p>
    </section>
  @else
    <div class="ad-stats">
      @if ($balance && ($balance['ok'] ?? false))
        <div class="ad-stat"><span class="n">৳ {{ number_format((float) ($balance['amount'] ?? 0), 2) }}</span><span class="l">Steadfast balance</span></div>
      @else
        <div class="ad-stat"><span class="n">—</span><span class="l">Balance unavailable{{ !empty($balance['message']) ? ': ' . $balance['message'] : '' }}</span></div>
      @endif
      <div class="ad-stat {{ $ready->count() ? 'ad-stat-hot' : '' }}"><span class="n">{{ $ready->count() }}</span><span class="l">Confirmed, ready to send</span></div>
      <a class="ad-stat" href="{{ route('admin.courier.tracking') }}"><span class="n">{{ $recent->count() }}</span><span class="l">Recently sent → Tracking</span></a>
    </div>
  @endif

  <section class="ad-card">
    <h2>Ready to send</h2>
    @if ($ready->isEmpty())
      <p class="ad-muted">No confirmed orders are waiting. Orders appear here after you set them to "Confirmed".</p>
    @else
      <form method="POST" action="{{ route('admin.courier.send.bulk') }}"
            onsubmit="return confirm(event.submitter && event.submitter.dataset.one ? 'Send this order to Steadfast now?' : 'Send the ticked orders to Steadfast now?');">
        @csrf
        <div class="ad-scroll">
          <table class="ad-table">
            <tr><th><input type="checkbox" id="checkAll" aria-label="Select all"></th><th>Order</th><th>Customer</th><th>Address</th><th>COD amount</th><th></th></tr>
            @foreach ($ready as $o)
              <tr>
                <td><input type="checkbox" name="ids[]" value="{{ $o->id }}" class="rowCheck"></td>
                <td><a href="{{ route('admin.orders.show', $o->id) }}"><strong>#{{ $o->id }}</strong></a></td>
                <td>{{ $o->name }}<br><small class="ad-muted">{{ $o->phone }}</small></td>
                <td style="max-width:320px">{{ $o->address }}</td>
                <td>৳ {{ number_format($o->total) }}</td>
                <td>
                  <button type="submit" class="ad-btn" data-one="1" formaction="{{ route('admin.courier.send', $o->id) }}"
                          {{ $configured ? '' : 'disabled' }}>Send</button>
                </td>
              </tr>
            @endforeach
          </table>
        </div>
        <div class="ad-submit" style="margin-top:12px">
          <button type="submit" class="ad-btn ad-btn-gray" {{ $configured ? '' : 'disabled' }}>Send ticked orders (max 20)</button>
        </div>
      </form>
      <script>
        document.getElementById('checkAll').addEventListener('change', function () {
          document.querySelectorAll('.rowCheck').forEach(function (c) { c.checked = this.checked; }, this);
        });
      </script>
    @endif
  </section>

  <section class="ad-card">
    <div class="ad-card-head"><h2>Recently sent</h2><a href="{{ route('admin.courier.tracking') }}">All parcels →</a></div>
    @if ($recent->isEmpty())
      <p class="ad-muted">Nothing sent yet.</p>
    @else
      <div class="ad-scroll">
        <table class="ad-table">
          <tr><th>Order</th><th>Customer</th><th>Consignment</th><th>Tracking code</th><th>Courier status</th><th>Sent</th><th>Sent by</th></tr>
          @foreach ($recent as $o)
            <tr>
              <td><a href="{{ route('admin.orders.show', $o->id) }}"><strong>#{{ $o->id }}</strong></a></td>
              <td>{{ $o->name }}</td>
              <td>{{ $o->consignment_id }}</td>
              <td>{{ $o->tracking_code ?: '—' }}</td>
              <td><span class="ad-st {{ \App\Support\Steadfast::badge($o->courier_status) }}">{{ \App\Support\Steadfast::label($o->courier_status) }}</span></td>
              <td>{{ optional($o->courier_sent_at)->format('d M, h:i A') }}</td>
              <td>{{ $o->courier_sent_by_name ?: '—' }}</td>
            </tr>
          @endforeach
        </table>
      </div>
    @endif
  </section>
@endsection
