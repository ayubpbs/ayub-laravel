@extends('admin.layout')

@section('title', 'Website Menu')

@section('content')
  <div class="ad-bar">
    <h1 class="ad-title">🧭 Website Menu</h1>
    @if ($ready)
      <a class="ad-btn" href="{{ route('admin.menu.create') }}">＋ Add menu item</a>
    @endif
  </div>

  @if (!$ready)
    <section class="ad-card">
      <h2>One-time setup</h2>
      <p>The menu needs a small table in the database. Click the button once (or run <code>php artisan migrate</code>). Until then the website keeps showing its built-in menu.</p>
      <form action="{{ route('admin.menu.setup') }}" method="POST">
        @csrf
        <button type="submit" class="ad-btn">Create menu table</button>
      </form>
    </section>
  @else
    <section class="ad-card">
      @if ($custom)
        <p style="margin-top:0"><span class="ad-st st-delivered">Custom menu</span> The header of the website shows the items below.</p>
      @else
        <p style="margin-top:0"><span class="ad-st st-pending">Default menu</span> The website is showing the menu that is built into the code. To change it, load it here and edit it, or add your own items (they will replace the default menu).</p>
      @endif

      <div class="ad-submit" style="display:flex;gap:10px;flex-wrap:wrap">
        <form action="{{ route('admin.menu.import') }}" method="POST" onsubmit="return confirm('{{ $custom ? 'This replaces your saved menu with the default menu. Continue?' : 'Load the default menu into the editor?' }}');">
          @csrf
          <button type="submit" class="ad-btn ad-btn-gray">⤓ Load default menu to edit</button>
        </form>
        @if ($custom)
          <form action="{{ route('admin.menu.reset') }}" method="POST" onsubmit="return confirm('Delete the whole saved menu? The website will show the default menu again.');">
            @csrf
            <button type="submit" class="ad-btn ad-btn-gray">↺ Back to default menu</button>
          </form>
        @endif
      </div>
    </section>

    <section class="ad-card">
      @if ($rows->isEmpty())
        <p class="ad-muted">No menu items saved yet. Click "Add menu item", or load the default menu and change it.</p>
      @else
        <div class="ad-scroll">
          <table class="ad-table">
            <tr><th>Order</th><th>Menu text</th><th>Type</th><th>Goes to</th><th>Opens in</th><th>Status</th><th></th></tr>
            @foreach ($rows as $item)
              <tr>
                <td style="white-space:nowrap">
                  <form action="{{ route('admin.menu.move', $item->id) }}" method="POST" style="display:inline">
                    @csrf <input type="hidden" name="dir" value="up">
                    <button type="submit" class="ad-btn ad-btn-gray" style="padding:2px 9px" title="Move up" {{ $item->is_first ? 'disabled' : '' }}>▲</button>
                  </form>
                  <form action="{{ route('admin.menu.move', $item->id) }}" method="POST" style="display:inline">
                    @csrf <input type="hidden" name="dir" value="down">
                    <button type="submit" class="ad-btn ad-btn-gray" style="padding:2px 9px" title="Move down" {{ $item->is_last ? 'disabled' : '' }}>▼</button>
                  </form>
                </td>
                <td>
                  @if ($item->depth)<span class="ad-muted">&nbsp;&nbsp;↳ </span>@endif
                  <a href="{{ route('admin.menu.edit', $item->id) }}"><strong>{{ $item->label }}</strong></a>
                  @if ($item->type === 'dropdown' && $rows->where('parent_id', $item->id)->isEmpty())
                    <br><small class="ad-muted">Empty dropdown: it is not shown on the website until you add a sub-item.</small>
                  @endif
                </td>
                <td>{{ $item->type === 'dropdown' ? 'Dropdown' : ($item->type === 'home' ? 'Home page' : ($item->type === 'category' ? 'Category' : ($item->type === 'search' ? 'Product search' : 'Custom link'))) }}</td>
                <td>
                  @if ($item->type === 'home')
                    Home page
                  @elseif ($item->type === 'dropdown')
                    <span class="ad-muted">Opens a sub-menu</span>
                  @elseif ($item->type === 'category')
                    @if ($cats->has((int) $item->category_id))
                      {{ $cats->get((int) $item->category_id)->label }}
                    @else
                      <span class="ad-red">Category deleted: this link is hidden</span>
                    @endif
                  @elseif ($item->type === 'search')
                    Search for “{{ $item->search_term }}”
                  @else
                    <span style="word-break:break-all">{{ $item->url }}</span>
                  @endif
                </td>
                <td>{{ $item->type === 'dropdown' ? '—' : ($item->new_tab ? 'New tab' : 'Same tab') }}</td>
                <td>
                  <span class="ad-st {{ $item->is_active ? 'st-delivered' : 'st-cancelled' }}">{{ $item->is_active ? 'Visible' : 'Hidden' }}</span>
                  <form action="{{ route('admin.menu.toggle', $item->id) }}" method="POST" style="display:inline">
                    @csrf
                    <button type="submit" class="ad-link">{{ $item->is_active ? 'Hide' : 'Show' }}</button>
                  </form>
                </td>
                <td class="ad-actions">
                  <a class="ad-link" href="{{ route('admin.menu.edit', $item->id) }}">Edit</a>
                  @if ($item->type === 'dropdown')
                    <a class="ad-link" href="{{ route('admin.menu.create', ['parent' => $item->id]) }}">＋ Sub-item</a>
                  @endif
                  <form action="{{ route('admin.menu.destroy', $item->id) }}" method="POST" onsubmit="return confirm('{{ $item->type === 'dropdown' ? 'Delete this dropdown and all its sub-items?' : 'Delete this menu item?' }}');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="ad-link ad-del">Delete</button>
                  </form>
                </td>
              </tr>
            @endforeach
          </table>
        </div>
        <p class="ad-hint">The menu has two levels: main items, and sub-items inside a "Dropdown". Use ▲ ▼ to change the order. Hidden items are not shown on the website. If you delete every item, the default menu comes back (to show nothing, hide the items instead).</p>
      @endif
    </section>
  @endif
@endsection
