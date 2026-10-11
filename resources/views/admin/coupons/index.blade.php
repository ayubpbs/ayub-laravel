@extends('admin.layout')

@section('title', 'Coupons')

@section('content')
  <div class="ad-bar">
    <h1 class="ad-title">🎟️ Coupons</h1>
    <a class="ad-btn" href="{{ route('admin.coupons.create') }}">＋ New Coupon</a>
  </div>

  <section class="ad-card">
    @if ($coupons->isEmpty())
      <p class="ad-muted">No coupons yet. Create one and customers can use it on the checkout page.</p>
    @else
      <div class="ad-scroll">
        <table class="ad-table">
          <tr><th>Code</th><th>Discount</th><th>Min. order</th><th>Used</th><th>Valid</th><th>Status</th><th></th></tr>
          @foreach ($coupons as $c)
            <tr>
              <td>
                <a href="{{ route('admin.coupons.edit', $c->id) }}"><strong>{{ $c->code }}</strong></a>
                @if ($c->note)<br><small class="ad-muted">{{ $c->note }}</small>@endif
              </td>
              <td>
                {{ $c->label() }}
                @if ($c->type === 'percent' && $c->max_discount)<br><small class="ad-muted">up to ৳ {{ number_format($c->max_discount) }}</small>@endif
              </td>
              <td>{{ $c->min_order > 0 ? '৳ ' . number_format($c->min_order) : '—' }}</td>
              <td>{{ $c->used_count }}{{ $c->usage_limit ? ' / ' . $c->usage_limit : '' }}</td>
              <td>
                @if ($c->starts_at || $c->expires_at)
                  {{ $c->starts_at ? $c->starts_at->format('d M Y') : 'Any time' }} → {{ $c->expires_at ? $c->expires_at->format('d M Y') : 'No expiry' }}
                @else
                  Always
                @endif
              </td>
              <td><span class="ad-st {{ $c->statusBadge() }}">{{ $c->statusLabel() }}</span></td>
              <td class="ad-actions">
                <a class="ad-link" href="{{ route('admin.coupons.edit', $c->id) }}">Edit</a>
                <form action="{{ route('admin.coupons.destroy', $c->id) }}" method="POST" onsubmit="return confirm('Really delete this coupon? Past orders keep their discount.');">
                  @csrf
                  @method('DELETE')
                  <button type="submit" class="ad-link ad-del">Delete</button>
                </form>
              </td>
            </tr>
          @endforeach
        </table>
      </div>
    @endif
  </section>
@endsection
