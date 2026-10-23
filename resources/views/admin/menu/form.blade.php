@extends('admin.layout')

@php
  $editing  = $item->exists;
  $hasOld   = !empty(old());
  $type     = old('type', $item->type ?: 'category');
  $parentId = old('parent_id', $item->parent_id);
  $isActive = $hasOld ? (bool) old('is_active') : ($editing ? (bool) $item->is_active : true);
  $newTab   = $hasOld ? (bool) old('new_tab') : (bool) $item->new_tab;
@endphp

@section('title', $editing ? 'Edit menu item' : 'New menu item')

@section('content')
  <p class="ad-crumbs"><a href="{{ route('admin.menu.index') }}">← Website Menu</a></p>
  <h1 class="ad-title">{{ $editing ? 'Edit “' . $item->label . '”' : 'New menu item' }}</h1>

  <form class="ad-card ad-form" method="POST" id="menu-form"
        action="{{ $editing ? route('admin.menu.update', $item->id) : route('admin.menu.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif

    <div class="ad-row">
      <label>Menu text *
        <input type="text" name="label" value="{{ old('label', $item->label) }}" required maxlength="80" placeholder="e.g. Solar">
      </label>
      <label>Place it in
        <select name="parent_id" id="m-parent" {{ $hasKids ? 'disabled' : '' }}>
          <option value="">Main menu (top level)</option>
          @foreach ($dropdowns as $d)
            @if (!$editing || $d->id !== $item->id)
              <option value="{{ $d->id }}" {{ (string) $parentId === (string) $d->id ? 'selected' : '' }}>Inside dropdown: {{ $d->label }}</option>
            @endif
          @endforeach
        </select>
        @if ($hasKids)<small>This dropdown has sub-items, so it stays in the main menu.</small>@endif
      </label>
      <label>Type *
        <select name="type" id="m-type">
          @foreach (\App\Models\NavMenuItem::types() as $key => $label)
            <option value="{{ $key }}" data-top-only="{{ in_array($key, ['home', 'dropdown'], true) ? 1 : 0 }}" {{ $type === $key ? 'selected' : '' }}>{{ $label }}</option>
          @endforeach
        </select>
      </label>
    </div>
    @if ($hasKids)<input type="hidden" name="parent_id" value="">@endif

    <div class="ad-row" id="w-category">
      <label>Category *
        <select name="category_id">
          <option value="">Choose a category</option>
          @foreach ($categories as $c)
            <option value="{{ $c->id }}" {{ (string) old('category_id', $item->category_id) === (string) $c->id ? 'selected' : '' }}>{{ $c->depth ? '— ' : '' }}{{ $c->label }}</option>
          @endforeach
        </select>
        <small>The link goes to this category's page.</small>
      </label>
    </div>

    <div class="ad-row" id="w-search">
      <label>Search word *
        <input type="text" name="search_term" value="{{ old('search_term', $item->search_term) }}" maxlength="150" placeholder="e.g. Surge Protector">
        <small>The link shows the products found by this word.</small>
      </label>
    </div>

    <div class="ad-row" id="w-url">
      <label>Link *
        <input type="text" name="url" value="{{ old('url', $item->url) }}" maxlength="500" placeholder="https://example.com  or  /pages/about  or  #contact">
        <small>A full https:// link, a path on this site (starting with /), #section, mailto: or tel:</small>
      </label>
    </div>

    <p class="ad-hint" id="w-home" style="margin:0 0 10px">The link goes to the home page.</p>
    <p class="ad-hint" id="w-dropdown" style="margin:0 0 10px">A dropdown is only a heading. After saving, use "＋ Sub-item" on the menu page to put links inside it. An empty dropdown is not shown on the website.</p>

    <label class="ad-check" id="w-newtab">
      <input type="checkbox" name="new_tab" value="1" {{ $newTab ? 'checked' : '' }}> Open in a new tab
    </label>
    <label class="ad-check">
      <input type="checkbox" name="is_active" value="1" {{ $isActive ? 'checked' : '' }}> Show on the website
    </label>

    <div class="ad-submit">
      <button type="submit" class="ad-btn">{{ $editing ? 'Save changes' : 'Add to menu' }}</button>
      <a class="ad-btn ad-btn-gray" href="{{ route('admin.menu.index') }}">Cancel</a>
    </div>
  </form>

  <script>
    (function () {
      var $ = function (id) { return document.getElementById(id); };
      var typeEl = $('m-type'), parentEl = $('m-parent');

      function show(id, on) { $(id).style.display = on ? '' : 'none'; }

      function sync() {
        var sub = !!(parentEl && parentEl.value);
        // inside a dropdown only category / search / custom link make sense
        Array.prototype.forEach.call(typeEl.options, function (o) {
          var topOnly = o.getAttribute('data-top-only') === '1';
          o.disabled = sub && topOnly;
          if (o.disabled && o.selected) { typeEl.value = 'category'; }
        });
        var t = typeEl.value;
        show('w-category', t === 'category');
        show('w-search', t === 'search');
        show('w-url', t === 'url');
        show('w-home', t === 'home');
        show('w-dropdown', t === 'dropdown');
        show('w-newtab', t !== 'dropdown');
      }

      typeEl.addEventListener('change', sync);
      if (parentEl) { parentEl.addEventListener('change', sync); }
      sync();
    })();
  </script>
@endsection
