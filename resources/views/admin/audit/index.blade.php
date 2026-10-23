@extends('admin.layout')

@section('title', 'Audit Log')

@section('content')
  <div class="ad-bar">
    <h1 class="ad-title">Audit Log</h1>
  </div>

  @if (!$ready)
    <div class="ad-flash ad-err">The audit table is missing. Run <code>php artisan migrate</code>, then reload this page.</div>
  @else
    <form method="GET" action="{{ route('admin.audit.index') }}" class="ad-filter">
      <select name="action">
        <option value="">Any action</option>
        @foreach ($actions as $a)
          <option value="{{ $a }}" {{ $f['action'] === $a ? 'selected' : '' }}>{{ $a }}</option>
        @endforeach
      </select>
      <input type="text" name="actor" value="{{ $f['actor'] }}" placeholder="Who (name)">
      <input type="text" name="phone" value="{{ $f['phone'] }}" placeholder="Customer mobile">
      <input type="text" name="q" value="{{ $f['q'] }}" placeholder="Words in the text">
      <input type="date" name="from" value="{{ $f['from'] }}" title="From date">
      <input type="date" name="to" value="{{ $f['to'] }}" title="To date">
      <button type="submit" class="ad-btn">Filter</button>
      @if (array_filter($f))<a class="ad-btn ad-btn-gray" href="{{ route('admin.audit.index') }}">Clear</a>@endif
    </form>

    <section class="ad-card">
      @if ($logs->isEmpty())
        <p class="ad-muted">Nothing found.</p>
      @else
        <div class="ad-scroll">
          <table class="ad-table">
            <tr><th>When</th><th>Who</th><th>Action</th><th>What</th><th>Customer</th><th>Details</th></tr>
            @foreach ($logs as $l)
              <tr>
                <td>{{ $l->created_at ? $l->created_at->format('d M Y, h:i A') : '—' }}</td>
                <td>{{ $l->actor_name ?: '—' }}<br><small class="ad-muted">{{ $l->ip }}</small></td>
                <td><code>{{ $l->action }}</code></td>
                <td>
                  {{ $l->summary }}
                  @if ($l->subject_id && $l->subject_type === 'claim')
                    <a class="ad-link" href="{{ route('admin.claims.show', $l->subject_id) }}">claim #{{ $l->subject_id }}</a>
                  @elseif ($l->subject_id && $l->subject_type === 'order')
                    <a class="ad-link" href="{{ route('admin.orders.show', $l->subject_id) }}">order #{{ $l->subject_id }}</a>
                  @endif
                </td>
                <td>
                  @if ($l->customer_phone)
                    <a class="ad-link" href="{{ route('admin.customers.show', $l->customer_phone) }}">{{ $l->customer_phone }}</a>
                  @else — @endif
                </td>
                <td>
                  @if ($l->old_values || $l->new_values)
                    <details>
                      <summary>Show</summary>
                      @if ($l->old_values)<div><strong>Before</strong><pre style="white-space:pre-wrap;margin:4px 0">{{ json_encode($l->old_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></div>@endif
                      @if ($l->new_values)<div><strong>After</strong><pre style="white-space:pre-wrap;margin:4px 0">{{ json_encode($l->new_values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre></div>@endif
                    </details>
                  @else — @endif
                </td>
              </tr>
            @endforeach
          </table>
        </div>
        <div class="ad-pager">{{ $logs->links('pagination::simple-default') }}</div>
      @endif
    </section>
  @endif
@endsection
