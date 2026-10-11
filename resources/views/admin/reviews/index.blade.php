@extends('admin.layout')

@section('title', 'Reviews')

@section('content')
  <div class="ad-bar">
    <h1 class="ad-title">Customer Reviews</h1>
  </div>

  @if (!$available)
    <section class="ad-card">
      <p class="ad-muted">Reviews are not set up yet. Run <code>php artisan migrate</code> once to create the reviews table.</p>
    </section>
  @else
    @php
      $tabs = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'];
    @endphp
    <div style="display:flex;flex-wrap:wrap;gap:8px;margin:0 0 14px">
      @foreach ($tabs as $key => $label)
        @php $n = $key === 'all' ? $counts->sum() : ($counts[$key] ?? 0); @endphp
        <a class="ad-btn {{ $status === $key ? '' : 'ad-btn-gray' }}" href="{{ route('admin.reviews.index', ['status' => $key]) }}">{{ $label }} ({{ $n }})</a>
      @endforeach
    </div>

    <section class="ad-card">
      @if ($reviews->isEmpty())
        <p class="ad-muted">No reviews here.</p>
      @else
        <div class="ad-scroll">
          <table class="ad-table">
            <tr><th>Product</th><th>Customer</th><th>Rating</th><th>Review</th><th>Date</th><th>Status</th><th></th></tr>
            @foreach ($reviews as $r)
              <tr>
                <td>
                  @if ($r->product)
                    <a href="{{ route('admin.products.edit', $r->product->id) }}"><strong>{{ $r->product->title }}</strong></a>
                  @else
                    <span class="ad-muted">(deleted product)</span>
                  @endif
                </td>
                <td>{{ $r->name }}</td>
                <td style="white-space:nowrap;color:#f5a623">{{ $r->stars() }}</td>
                <td style="max-width:360px;white-space:normal">{!! $r->comment ? nl2br(e($r->comment)) : '<span class="ad-muted">— no comment —</span>' !!}</td>
                <td style="white-space:nowrap">{{ $r->created_at->format('d M Y') }}</td>
                <td>
                  @if ($r->status === 'approved') <span class="ad-st st-delivered">Approved</span>
                  @elseif ($r->status === 'rejected') <span class="ad-st st-cancelled">Rejected</span>
                  @else <span class="ad-st st-shipped">Pending</span>
                  @endif
                </td>
                <td class="ad-actions">
                  @if ($r->status !== 'approved')
                    <form action="{{ route('admin.reviews.approve', $r->id) }}" method="POST">
                      @csrf
                      <button type="submit" class="ad-link">Approve</button>
                    </form>
                  @endif
                  @if ($r->status !== 'rejected')
                    <form action="{{ route('admin.reviews.reject', $r->id) }}" method="POST">
                      @csrf
                      <button type="submit" class="ad-link">Reject</button>
                    </form>
                  @endif
                  <form action="{{ route('admin.reviews.destroy', $r->id) }}" method="POST" onsubmit="return confirm('Really delete this review?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="ad-link ad-del">Delete</button>
                  </form>
                </td>
              </tr>
            @endforeach
          </table>
        </div>
        <div class="ad-pager">{{ $reviews->links('pagination::simple-default') }}</div>
      @endif
    </section>
  @endif
@endsection
