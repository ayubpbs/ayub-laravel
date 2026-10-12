@extends('admin.layout')

@section('title', 'Staff & Permissions')

@section('content')
  <div class="ad-bar">
    <h1 class="ad-title">🛡️ Staff &amp; Permissions</h1>
    <a class="ad-btn" href="{{ route('admin.staff.create') }}">＋ New Staff</a>
  </div>

  <section class="ad-card">
    <p class="ad-hint" style="margin:0 0 14px">
      You are the <strong>Owner</strong>: you sign in with the username and password in your <code>.env</code> file and can do everything.
      Staff sign in on the same login page with their own username and password, and only see the tasks you tick for them.
      Settings and this page are always owner only.
    </p>

    @if ($staff->isEmpty())
      <p class="ad-muted">No staff yet. Add one and choose what they may do.</p>
    @else
      <div class="ad-scroll">
        <table class="ad-table">
          <tr><th>Name</th><th>Username</th><th>Can do</th><th>Sent to courier</th><th>Last login</th><th>Status</th><th></th></tr>
          @foreach ($staff as $s)
            @php
              $names = [];
              foreach ((array) $s->permissions as $key) { if (isset($labels[$key])) { $names[] = $labels[$key][0]; } }
            @endphp
            <tr>
              <td><a href="{{ route('admin.staff.edit', $s->id) }}"><strong>{{ $s->name }}</strong></a></td>
              <td>{{ $s->username }}</td>
              <td style="max-width:340px"><small class="ad-muted">{{ $names ? implode(', ', $names) : 'Nothing yet' }}</small></td>
              <td>{{ $sent[$s->id] ?? 0 }}@if (($sent[$s->id] ?? 0) > 0) <a class="ad-link" href="{{ route('admin.courier.tracking', ['by' => $s->name]) }}">view</a>@endif</td>
              <td>{{ $s->last_login_at ? $s->last_login_at->diffForHumans() : 'never' }}</td>
              <td><span class="ad-st {{ $s->is_active ? 'st-delivered' : 'st-cancelled' }}">{{ $s->is_active ? 'Active' : 'Switched off' }}</span></td>
              <td class="ad-actions">
                <a class="ad-link" href="{{ route('admin.staff.edit', $s->id) }}">Edit</a>
                <form action="{{ route('admin.staff.toggle', $s->id) }}" method="POST">
                  @csrf
                  <button type="submit" class="ad-link">{{ $s->is_active ? 'Switch off' : 'Switch on' }}</button>
                </form>
                <form action="{{ route('admin.staff.destroy', $s->id) }}" method="POST" onsubmit="return confirm('Delete this staff account? Orders they sent to the courier keep their name.');">
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
