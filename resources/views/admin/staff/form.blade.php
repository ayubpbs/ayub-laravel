@extends('admin.layout')

@php
  $editing = $member->exists;
  $chosen  = old('permissions', (array) $member->permissions);
@endphp

@section('title', $editing ? 'Edit Staff' : 'New Staff')

@section('content')
  <p class="ad-crumbs"><a href="{{ route('admin.staff.index') }}">← All staff</a></p>
  <h1 class="ad-title">{{ $editing ? 'Edit ' . $member->name : 'New Staff' }}</h1>

  <form class="ad-card ad-form" method="POST" action="{{ $editing ? route('admin.staff.update', $member->id) : route('admin.staff.store') }}" autocomplete="off">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="ad-row">
      <label>Name *
        <input type="text" name="name" value="{{ old('name', $member->name) }}" required maxlength="100" placeholder="e.g. Rahim">
        <small>This name is written next to every order this person sends to the courier.</small>
      </label>
      <label>Username *
        <input type="text" name="username" value="{{ old('username', $member->username) }}" required minlength="3" maxlength="30" placeholder="e.g. rahim" autocapitalize="none" spellcheck="false">
        <small>Small letters, numbers, dot, dash or underscore.</small>
      </label>
      <label>Password {{ $editing ? '' : '*' }}
        <input type="text" name="password" value="" {{ $editing ? '' : 'required' }} minlength="8" maxlength="100" placeholder="{{ $editing ? 'Leave empty to keep the current password' : 'At least 8 characters' }}" autocomplete="new-password">
        <small>Give it to this person yourself. Only you can change it.</small>
      </label>
    </div>

    <div>
      <div class="ad-card-head" style="margin:6px 0 8px">
        <strong>What can this person do?</strong>
        <span><a href="#" class="ad-link" id="perm-all">Tick all</a> · <a href="#" class="ad-link" id="perm-none">Clear</a></span>
      </div>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:10px">
        @foreach ($perms as $key => $info)
          <label class="ad-check" style="align-items:flex-start;border:1px solid #e5e7eb;border-radius:10px;padding:10px 12px">
            <input type="checkbox" class="perm-box" name="permissions[]" value="{{ $key }}" {{ in_array($key, (array) $chosen, true) ? 'checked' : '' }}>
            <span><strong>{{ $info[0] }}</strong><br><small class="ad-muted">{{ $info[1] }}</small></span>
          </label>
        @endforeach
      </div>
      <p class="ad-hint">Settings and Staff &amp; Permissions are never given to staff. Menus a person cannot use are hidden for them, and opening such a page shows "Access denied".</p>
    </div>

    <div class="ad-submit">
      <button type="submit" class="ad-btn">{{ $editing ? 'Update' : 'Add Staff' }}</button>
      <a class="ad-btn ad-btn-gray" href="{{ route('admin.staff.index') }}">Cancel</a>
    </div>
  </form>

  <script>
    (function () {
      function setAll(v) { document.querySelectorAll('.perm-box').forEach(function (b) { b.checked = v; }); }
      document.getElementById('perm-all').addEventListener('click', function (e) { e.preventDefault(); setAll(true); });
      document.getElementById('perm-none').addEventListener('click', function (e) { e.preventDefault(); setAll(false); });
    })();
  </script>
@endsection
