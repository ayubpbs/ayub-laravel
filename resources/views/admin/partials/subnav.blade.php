{{--
    Topbar sub-navigation + breadcrumb for the admin panel.

    It does NOT define any menu of its own. sidebar.blade.php and accounting-menu.blade.php share their
    (already permission-filtered) menus as $adminNavMenu / $adminNavAccounting, and this partial only
    re-renders the part that belongs to the module of the current route. So the sidebar and the topbar
    always show the same links, the same active state and the same permissions.

    Row 1: the sections of the current module (Accounting: Dashboard, Income, Expense ...).
    Row 2: only when the active Accounting section has children (Income -> All Income, Add Income).
    Modules with no submenu (Dashboard, Customers, Settings ...) get no bar, only a breadcrumb when deeper than the module page.
    Must be included AFTER the sidebar partial (see layout.blade.php).
--}}
@php
    $navMenu   = $adminNavMenu ?? [];
    $navAcc    = $adminNavAccounting ?? [];
    $pageTitle = trim(strip_tags($__env->yieldContent('title')));

    // Where a menu item leads: its own url, or the first url among its children
    $landing = function (array $item) {
        if (!empty($item['url'])) {
            return $item['url'];
        }
        foreach (($item['children'] ?? []) as $c) {
            if (!empty($c['url'])) {
                return $c['url'];
            }
        }

        return null;
    };

    // Which module is the current route in? (first sidebar item that is "on")
    $module = null;
    $isAcc  = false;
    foreach ($navMenu as $it) {
        if (!empty($it['view'])) {
            if ($it['view'] === 'admin.partials.accounting-menu' && $navAcc && request()->routeIs('admin.accounting.*')) {
                $module = $it;
                $isAcc  = true;
                break;
            }
            continue;
        }
        if (!empty($it['on'])) {
            $module = $it;
            break;
        }
    }

    $row1 = [];
    $row2 = [];
    $crumbs = [];
    $leaf = false; // true once the breadcrumb ends on a real menu item

    if ($module) {
        $crumbs[] = ['label' => 'Admin', 'url' => route('admin.dashboard')];

        if ($isAcc) {
            $crumbs[] = ['label' => $module['label'], 'url' => $landing(['children' => $navAcc])];

            $section = null;
            foreach ($navAcc as $s) {
                $url = $landing($s);
                if (!$url) {
                    continue;
                }
                $row1[] = ['label' => $s['label'], 'url' => $url, 'on' => !empty($s['on'])];
                if (!empty($s['on'])) {
                    $section = $s;
                }
            }

            if ($section) {
                if (!empty($section['children'])) {
                    $crumbs[] = ['label' => $section['label'], 'url' => $landing($section)];
                    foreach ($section['children'] as $c) {
                        if (empty($c['url'])) {
                            continue;
                        }
                        $row2[] = ['label' => $c['label'], 'url' => $c['url'], 'on' => !empty($c['on'])];
                        if (!empty($c['on'])) {
                            $crumbs[] = ['label' => $c['label']];
                            $leaf = true;
                        }
                    }
                } else {
                    $crumbs[] = ['label' => $section['label']];
                    $leaf = true;
                }
            }
        } elseif (!empty($module['children'])) {
            $crumbs[] = ['label' => $module['label'], 'url' => $landing($module)];
            foreach ($module['children'] as $c) {
                if (empty($c['url'])) {
                    continue; // "coming soon" items are not links
                }
                $row1[] = [
                    'label' => $c['label'], 'url' => $c['url'], 'on' => !empty($c['on']),
                    'count' => $c['count'] ?? null, 'hot' => !empty($c['hot']),
                ];
                if (!empty($c['on'])) {
                    $crumbs[] = ['label' => $c['label']];
                    $leaf = true;
                }
            }
        } else {
            $crumbs[] = ['label' => $module['label'], 'url' => $module['url'] ?? null];
            $leaf = true;
        }

        // A page that is not a menu item (an order, a product being edited ...): end on the page title
        $lastLabel = $crumbs ? end($crumbs)['label'] : '';
        if (!$leaf && $pageTitle !== '' && strcasecmp($pageTitle, $lastLabel) !== 0) {
            $crumbs[] = ['label' => $pageTitle];
        } elseif ($leaf === true && !$row1 && count($crumbs) === 2 && $pageTitle !== '' && strcasecmp($pageTitle, $lastLabel) !== 0
                && request()->url() !== strtok((string) ($module['url'] ?? ''), '?')) {
            // single-page module (Customers, Coupons ...) on a deeper page such as one customer, not on its own list page
            $crumbs[] = ['label' => $pageTitle];
        }
    }

    $showCrumbs = count($crumbs) >= 3;
@endphp

@if ($row1 || $showCrumbs)
  <nav class="ad-subnav" aria-label="Section navigation">
    <div class="ad-subnav-in">
      @if ($showCrumbs)
        <ol class="ad-crumbs">
          @foreach ($crumbs as $i => $c)
            @if ($loop->last || empty($c['url']))
              <li aria-current="page">{{ $c['label'] }}</li>
            @else
              <li><a href="{{ $c['url'] }}">{{ $c['label'] }}</a></li>
            @endif
          @endforeach
        </ol>
      @endif

      @if ($row1)
        <div class="ad-subnav-row">
          @foreach ($row1 as $l)
            <a class="ad-subnav-link {{ $l['on'] ? 'on' : '' }}" href="{{ $l['url'] }}" @if ($l['on']) aria-current="page" @endif>
              <span>{{ $l['label'] }}</span>
              @if (isset($l['count']))<span class="ad-subnav-count {{ !empty($l['hot']) && $l['count'] > 0 ? 'hot' : '' }}">{{ $l['count'] }}</span>@endif
            </a>
          @endforeach
        </div>
      @endif

      @if ($row2)
        <div class="ad-subnav-row ad-subnav-row2">
          @foreach ($row2 as $l)
            <a class="ad-subnav-link {{ $l['on'] ? 'on' : '' }}" href="{{ $l['url'] }}" @if ($l['on']) aria-current="page" @endif><span>{{ $l['label'] }}</span></a>
          @endforeach
        </div>
      @endif
    </div>
  </nav>
@endif
