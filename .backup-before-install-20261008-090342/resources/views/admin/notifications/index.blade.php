@extends('admin.layout')

@section('title', 'Notifications')

@section('content')
  <div class="ad-bar">
    <h1 class="ad-title">Notifications @if ($unread > 0)<span class="sb-badge nt-title-badge">{{ $unread }}</span>@endif</h1>
    <div class="ad-actions">
      @if ($unread > 0)
        <form method="POST" action="{{ route('admin.notifications.read.all') }}">@csrf
          <button type="submit" class="ad-btn">✓ Mark all as read</button>
        </form>
      @endif
      <form method="POST" action="{{ route('admin.notifications.clear') }}" onsubmit="return confirm('Delete all read notifications?')">@csrf
        <button type="submit" class="ad-btn ad-btn-gray">🗑 Clear read</button>
      </form>
    </div>
  </div>

  <div class="ad-tabs">
    <a href="{{ route('admin.notifications.index', array_filter(['type' => $type])) }}" class="{{ $tab === 'all' ? 'on' : '' }}">All</a>
    <a href="{{ route('admin.notifications.index', array_filter(['tab' => 'unread', 'type' => $type])) }}" class="{{ $tab === 'unread' ? 'on' : '' }}">Unread ({{ $unread }})</a>
  </div>

  <div class="ad-tabs nt-types">
    <a href="{{ route('admin.notifications.index', array_filter(['tab' => $tab === 'unread' ? 'unread' : null])) }}" class="{{ $type === '' ? 'on' : '' }}">Everything</a>
    @foreach (\App\Models\AdminNotification::types() as $key => [$label, $icon])
      <a href="{{ route('admin.notifications.index', array_filter(['tab' => $tab === 'unread' ? 'unread' : null, 'type' => $key])) }}" class="{{ $type === $key ? 'on' : '' }}">
        {{ $icon }} {{ $label }}@if (($perType[$key] ?? 0) > 0) <span class="nt-n">{{ $perType[$key] }}</span>@endif
      </a>
    @endforeach
  </div>

  <section class="ad-card nt-card">
    @if ($notifications->isEmpty())
      <p class="ad-muted">{{ $tab === 'unread' ? 'You are all caught up — nothing unread.' : 'No notifications yet. They appear here when orders, payments or stock change.' }}</p>
    @else
      <ul class="nt-list">
        @foreach ($notifications as $n)
          @php [$label, $icon, $cls] = $n->meta(); @endphp
          <li class="nt-item {{ $n->isUnread() ? 'unread' : '' }}">
            <span class="nt-ico {{ $cls }}">{{ $icon }}</span>
            <a class="nt-body" href="{{ route('admin.notifications.open', $n->id) }}">
              <span class="nt-title">{{ $n->title }}</span>
              @if ($n->message)<span class="nt-msg">{{ $n->message }}</span>@endif
              <span class="nt-meta"><span class="nt-tag {{ $cls }}">{{ $label }}</span> · {{ $n->created_at->diffForHumans() }} · {{ $n->created_at->format('d M, h:i A') }}</span>
            </a>
            @if ($n->isUnread())
              <form method="POST" action="{{ route('admin.notifications.read', $n->id) }}">@csrf
                <button type="submit" class="nt-mark" title="Mark as read">✓</button>
              </form>
            @endif
          </li>
        @endforeach
      </ul>
      <div class="ad-pager">{{ $notifications->links('pagination::simple-default') }}</div>
    @endif
  </section>
@endsection
