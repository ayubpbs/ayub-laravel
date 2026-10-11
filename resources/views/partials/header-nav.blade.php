{{-- Main header navigation (desktop mega menu + mobile accordion). Data: App\Support\NavMenu --}}
<nav class="main-nav" id="mainNav" aria-label="Main menu">
  <div class="nav-inner">
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="navList">
      <span class="nav-burger" aria-hidden="true"></span>
      <span>Menu</span>
    </button>

    <ul class="nav-list" id="navList">
      @foreach (\App\Support\NavMenu::items() as $item)
        @if (empty($item['children']))
          <li class="nav-item">
            <a class="nav-link @if ($item['active']) is-active @endif" href="{{ $item['url'] }}"
               @if ($item['active']) aria-current="page" @endif>{{ $item['label'] }}</a>
          </li>
        @else
          <li class="nav-item has-sub @if ($item['active']) is-active @endif">
            <button class="nav-link nav-trigger @if ($item['active']) is-active @endif" type="button"
                    aria-haspopup="true" aria-expanded="false">
              {{ $item['label'] }}<span class="nav-caret" aria-hidden="true"></span>
            </button>
            <ul class="mega" style="--cols: {{ $item['cols'] }}">
              @foreach ($item['children'] as $child)
                <li>
                  <a class="mega-link @if ($child['active']) is-active @endif" href="{{ $child['url'] }}"
                     @if ($child['active']) aria-current="page" @endif>{{ $child['label'] }}</a>
                </li>
              @endforeach
            </ul>
          </li>
        @endif
      @endforeach
    </ul>
  </div>
</nav>
