@extends('admin.layout')

@section('title', 'Claims')

@section('content')
  @php $ax = \App\Support\AdminAccess::class; @endphp

  @if (!$ready)
    <h1 class="ad-title">Claims</h1>
    <div class="ad-flash ad-err">The claim tables are missing. Run <code>php artisan migrate</code>, then reload this page.</div>
  @else
    <div class="ad-bar">
      <h1 class="ad-title">{{ $kind === 'warranty' ? 'Warranty' : '' }} {{ $status === '' ? 'Claims' : \App\Models\ServiceRequest::statuses()[$status] . ' Claims' }}</h1>
      <form method="GET" action="{{ route('admin.claims.index') }}" class="ad-filter" style="margin:0">
        @if ($status !== '')<input type="hidden" name="status" value="{{ $status }}">@endif
        <select name="kind">
          <option value="">All types</option>
          <option value="warranty" {{ $kind === 'warranty' ? 'selected' : '' }}>Warranty claims</option>
          <option value="exchange" {{ $kind === 'exchange' ? 'selected' : '' }}>Exchange / wrong product</option>
        </select>
        <select name="type">
          <option value="">Any claim type</option>
          @foreach (\App\Models\ServiceRequest::allTypes() as $k => $label)
            <option value="{{ $k }}" {{ $type === $k ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
        <input type="text" name="product" value="{{ $product }}" placeholder="Product">
        <input type="date" name="from" value="{{ $from }}" title="From date">
        <input type="date" name="to" value="{{ $to }}" title="To date">
        <input type="text" name="q" value="{{ $q }}" placeholder="Search name, mobile, claim or order no.">
        <button type="submit" class="ad-btn">Search</button>
        @if ($q !== '' || $type !== '' || $product !== '' || $from !== '' || $to !== '')<a class="ad-btn ad-btn-gray" href="{{ route('admin.claims.index', array_filter(['status' => $status, 'kind' => $kind])) }}">Clear</a>@endif
      </form>
      @if ($ax::can('claims_manage'))
        <a class="ad-btn" href="{{ route('admin.claims.create') }}">+ New claim</a>
        <a class="ad-btn" href="{{ route('admin.claims.warranty.create') }}">+ Warranty claim</a>
      @endif
    </div>

    <div class="ad-tabs">
      <a href="{{ route('admin.claims.index', array_filter(['kind' => $kind])) }}" class="{{ $status === '' ? 'on' : '' }}">All ({{ $counts->sum() }})</a>
      @foreach (\App\Models\ServiceRequest::statuses() as $key => $label)
        <a href="{{ route('admin.claims.index', array_filter(['status' => $key, 'kind' => $kind])) }}" class="{{ $status === $key ? 'on' : '' }}">{{ $label }} ({{ $counts[$key] ?? 0 }})</a>
      @endforeach
    </div>

    <section class="ad-card">
      @if ($claims->isEmpty())
        <p class="ad-muted">{{ $q !== '' ? 'No claims match your search.' : 'No claims yet.' }}</p>
      @else
        <div class="ad-scroll">
          <table class="ad-table">
            <tr><th>Claim</th><th>Opened</th><th>Customer</th><th>Order</th><th>Type</th><th>Solution</th><th>Status</th></tr>
            @foreach ($claims as $c)
              <tr>
                <td><a href="{{ route('admin.claims.show', $c->id) }}"><strong>#{{ $c->id }}</strong></a></td>
                <td>{{ $c->created_at->format('d M Y, h:i A') }}</td>
                <td>{{ $c->customer_name }}<br><small class="ad-muted">{{ $c->customer_phone }}</small></td>
                <td><a class="ad-link" href="{{ route('admin.orders.show', $c->order_id) }}">#{{ $c->order_id }}</a></td>
                <td>{{ $c->typeLabel() }}</td>
                <td>{{ $c->resolution ? ucfirst($c->resolution) : '—' }}</td>
                <td><span class="ad-st {{ \App\Models\ServiceRequest::badge($c->status) }}">{{ $c->statusLabel() }}</span></td>
              </tr>
            @endforeach
          </table>
        </div>
        <div class="ad-pager">{{ $claims->links('pagination::simple-default') }}</div>
      @endif
    </section>
  @endif
@endsection
