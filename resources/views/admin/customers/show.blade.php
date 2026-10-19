@extends('admin.layout')

@section('title', $name)

@section('content')
  <p class="ad-crumbs"><a href="{{ route('admin.customers.index') }}">← All customers</a></p>
  <h1 class="ad-title">{{ $name }}</h1>

  <div class="ad-stats">
    <div class="ad-stat"><span class="n">{{ $stats['orders'] }}</span><span class="l">Total orders</span></div>
    <div class="ad-stat"><span class="n">{{ $stats['delivered'] }}</span><span class="l">Delivered</span></div>
    <div class="ad-stat {{ $stats['failed'] > 0 ? 'ad-stat-hot' : '' }}"><span class="n">{{ $stats['failed'] }}</span><span class="l">Cancelled / Returned</span></div>
    <div class="ad-stat"><span class="n">৳ {{ number_format($stats['spent']) }}</span><span class="l">Delivered total</span></div>
  </div>

  <div class="ad-two">
    <section class="ad-card">
      <h2>Details</h2>
      <dl class="ad-dl">
        <dt>Mobile</dt><dd><a href="tel:{{ $phone }}">{{ $phone }}</a></dd>
        @if ($latest)
          <dt>Latest address</dt><dd>{{ $latest->address }}</dd>
          <dt>First order</dt><dd>{{ $stats['first']->format('d M Y') }}</dd>
          <dt>Last order</dt><dd>{{ $latest->created_at->format('d M Y, h:i A') }}</dd>
        @else
          <dt>Orders</dt><dd>No orders yet</dd>
        @endif
        @if ($names->count() > 1)
          <dt>Names used</dt><dd>{{ $names->implode(', ') }}</dd>
        @endif
        @if ($profile && $profile->notes)
          <dt>Internal note</dt><dd>{{ $profile->notes }}</dd>
        @endif
        @if ($profile)
          <dt>Profile added</dt><dd>{{ $profile->created_at->format('d M Y') }}{{ $profile->created_by_name ? ' by ' . $profile->created_by_name : '' }}</dd>
        @endif
        @if ($imported)
          <dt>Old site</dt><dd>{{ $imported->total_orders }} order(s) before this system{{ $imported->last_order ? ', last on ' . $imported->last_order->format('d M Y') : '' }}</dd>
        @endif
      </dl>
    </section>

    <section class="ad-card">
      <h2>Addresses used</h2>
      @php $oldAddress = $imported ? trim(implode(', ', array_filter([$imported->address, $imported->area, $imported->zone, $imported->city]))) : ''; @endphp
      @if ($addresses->isEmpty() && $oldAddress === '')
        <p class="ad-muted">No address yet.</p>
      @else
        <ul style="margin:0;padding-left:18px">
          @foreach ($addresses as $a)<li>{{ $a }}</li>@endforeach
          @if ($oldAddress !== '' && !$addresses->contains($oldAddress))<li>{{ $oldAddress }} <span class="ad-muted">(old site)</span></li>@endif
        </ul>
      @endif
    </section>
  </div>

  <section class="ad-card">
    <div class="ad-bar">
      <h2>Saved addresses</h2>
      <a class="ad-btn" href="{{ route('admin.customers.addresses.create', $phone) }}">+ Add address</a>
      @if (\App\Support\AdminAccess::can('orders_create'))
        <a class="ad-btn" href="{{ route('admin.orders.create', ['phone' => $phone]) }}">+ New order</a>
      @endif
    </div>
    @if ($saved->isEmpty())
      <p class="ad-muted">No saved address yet. Saved addresses are filled in automatically when you create an order.</p>
    @else
      <div class="ad-scroll">
        <table class="ad-table">
          <tr><th>Recipient</th><th>Address</th><th>Note</th><th></th></tr>
          @foreach ($saved as $a)
            <tr>
              <td>
                <strong>{{ $a->recipient_name }}</strong>
                @if ($a->is_default)<span class="ad-st st-delivered">Default</span>@endif
                <div class="ad-muted">{{ $a->phone }}</div>
              </td>
              <td>{{ $a->fullLine() }}</td>
              <td>{{ $a->landmark ?: '—' }}</td>
              <td class="ad-actions">
                <a class="ad-link" href="{{ route('admin.customers.addresses.edit', [$phone, $a->id]) }}">Edit</a>
                @unless ($a->is_default)
                  <form action="{{ route('admin.customers.addresses.default', [$phone, $a->id]) }}" method="POST">@csrf
                    <button type="submit" class="ad-link">Set default</button>
                  </form>
                @endunless
                <form action="{{ route('admin.customers.addresses.destroy', [$phone, $a->id]) }}" method="POST" onsubmit="return confirm('Delete this saved address? Past orders keep their own address.');">@csrf @method('DELETE')
                  <button type="submit" class="ad-link ad-del">Delete</button>
                </form>
              </td>
            </tr>
          @endforeach
        </table>
      </div>
    @endif
  </section>

  <section class="ad-card">
    <h2>Order history</h2>
    @if ($orders->isEmpty())
      <p class="ad-muted">No orders yet.</p>
    @else
    <div class="ad-scroll">
      <table class="ad-table">
        <tr><th>No.</th><th>Date</th><th>Items</th><th>Total</th><th>Payment</th><th>Status</th><th>Courier</th><th></th></tr>
        @foreach ($orders as $o)
          <tr>
            <td><a href="{{ route('admin.orders.show', $o->id) }}"><strong>#{{ $o->id }}</strong></a></td>
            <td>{{ $o->created_at->format('d M Y, h:i A') }}</td>
            <td>
              @foreach ($o->items as $i)
                <div>{{ $i->title }} × {{ $i->quantity }}</div>
              @endforeach
            </td>
            <td>৳ {{ number_format($o->total) }}</td>
            <td><span class="ad-st {{ \App\Models\Order::paymentBadge($o->paymentStatus()) }}">{{ $o->paymentLabel() }}</span></td>
            <td><span class="ad-st st-{{ $o->status }}">{{ $o->statusLabel() }}</span></td>
            <td>
              @if ($o->consignment_id)
                {{ \App\Support\Steadfast::label($o->courier_status) }}
                @if ($o->tracking_code)<div class="ad-muted">{{ $o->tracking_code }}</div>@endif
              @else
                —
              @endif
            </td>
            <td><a class="ad-link" href="{{ route('admin.orders.show', $o->id) }}">View</a></td>
          </tr>
        @endforeach
      </table>
    </div>
    @endif
  </section>

  @if ($activity->isNotEmpty())
    <section class="ad-card">
      <h2>Activity</h2>
      <div class="ad-scroll">
        <table class="ad-table">
          <tr><th>When</th><th>Who</th><th>What</th></tr>
          @foreach ($activity as $log)
            <tr>
              <td>{{ $log->created_at ? $log->created_at->format('d M Y, h:i A') : '—' }}</td>
              <td>{{ $log->actor_name ?: '—' }}</td>
              <td>{{ $log->summary }}</td>
            </tr>
          @endforeach
        </table>
      </div>
    </section>
  @endif
@endsection
