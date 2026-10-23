<?php

namespace App\Support;

use App\Models\Category;
use App\Models\NavMenuItem;

/**
 * Header navigation menu.
 *
 * - No database changes: the menu itself lives in definition() below.
 * - Once the admin saves a menu in Admin > Website Menu (table nav_menu_items), that menu is used instead
 *   (see fromDatabase() below). With no saved items, the built-in menu in definition() is used.
 * - A submenu item links to an existing category (route category.show) when a category
 *   with the same name exists in the database; otherwise it links to the existing
 *   product search (route category.index?q=...), so no link is ever broken.
 */
class NavMenu
{
    /**
     * Each child is either a string (used as label and search word)
     * or [label, search word].
     */
    public static function definition(): array
    {
        return [
            ['label' => 'Home', 'home' => true],

            ['label' => 'Smart Home', 'children' => [
                'Smart WiFi Circuit Breaker',
                'Smart Switch',
                'Smart Plug',
                'Smart Door Lock',
                'Smart Sensor',
                'Smart Home Accessories',
            ]],

            ['label' => 'Electrical', 'children' => [
                'MCB',
                'RCCB',
                'RCBO',
                'MCCB',
                'AFDD',
                ['SPD / Surge Protector', 'Surge Protector'],
                'Voltage Protector',
                'Changeover Switch',
                'ATS',
                'Electrical Accessories',
            ]],

            ['label' => 'Solar', 'children' => [
                'Solar Panel',
                'Solar Inverter',
                'Hybrid Inverter',
                'Solar Charge Controller',
                'Solar Battery',
                'Solar Accessories',
            ]],

            ['label' => 'Gadgets', 'children' => [
                'Mobile Accessories',
                'Memory Card',
                'USB Gadgets',
                'Computer Accessories',
                'Electronic Gadgets',
                'Other Accessories',
            ]],

            ['label' => 'CC Camera', 'children' => [
                'WiFi Camera',
                'IP Camera',
                'Bullet Camera',
                'Dome Camera',
                'Solar Camera',
                'CCTV Accessories',
                ['Memory Card for Camera', 'Camera Memory Card'],
            ]],
        ];
    }

    /**
     * The menu with real URLs and "active" flags for the current request.
     *
     * Submenu items link to the matching category (by name) when it exists in the database,
     * otherwise to the product search, so no link is ever broken.
     */
    public static function items(): array
    {
        if (NavMenuItem::hasItems()) {
            return self::fromDatabase();
        }

        $req      = request();
        $cats     = Category::query()->get(['id', 'label', 'parent_id']);
        $byLabel  = $cats->keyBy(function ($c) {
            return mb_strtolower(trim($c->label));
        });
        $currentQ = mb_strtolower(trim((string) $req->query('q', '')));

        // The category page being viewed (if any)
        $currentCat = $req->routeIs('category.show')
            ? $cats->firstWhere('id', (int) $req->route('id'))
            : null;

        $out = [];

        foreach (self::definition() as $top) {
            if (!empty($top['home'])) {
                $out[] = [
                    'label'    => $top['label'],
                    'url'      => route('home'),
                    'active'   => $req->routeIs('home'),
                    'children' => [],
                    'cols'     => 1,
                ];
                continue;
            }

            $children  = [];
            $anyActive = false;

            foreach ($top['children'] as $child) {
                [$label, $q] = is_array($child) ? $child : [$child, $child];
                $key = mb_strtolower(trim($label));

                if ($byLabel->has($key)) {
                    $cat    = $byLabel->get($key);
                    $url    = route('category.show', $cat->id);
                    $active = $currentCat && (int) $currentCat->id === (int) $cat->id;
                } else {
                    $url    = route('category.index', ['q' => $q]);
                    $active = $req->routeIs('category.index') && $currentQ === mb_strtolower(trim($q));
                }

                $anyActive  = $anyActive || $active;
                $children[] = ['label' => $label, 'url' => $url, 'active' => $active];
            }

            // A main category with the same name as the menu: add an "All ..." link on top
            $topCat = $byLabel->get(mb_strtolower(trim($top['label'])));
            if ($topCat && $topCat->parent_id === null) {
                $allActive = $currentCat && (int) $currentCat->id === (int) $topCat->id;
                $inside    = $currentCat && (int) $currentCat->parent_id === (int) $topCat->id;

                array_unshift($children, [
                    'label'  => 'All ' . $top['label'],
                    'url'    => route('category.show', $topCat->id),
                    'active' => $allActive,
                ]);
                $anyActive = $anyActive || $allActive || $inside;
            }

            $count = count($children);

            $out[] = [
                'label'    => $top['label'],
                'url'      => null,
                'active'   => $anyActive,
                'children' => $children,
                'cols'     => $count >= 9 ? 3 : ($count >= 5 ? 2 : 1),
            ];
        }

        return $out;
    }
    // ------------------------------------------------------------------
    // Menu saved by the admin (Admin > Website Menu)
    // ------------------------------------------------------------------

    /** Same shape as items(): label, url, active, children, cols (+ new_tab). Hidden items and broken links are left out. */
    private static function fromDatabase(): array
    {
        $req      = request();
        $all      = NavMenuItem::where('is_active', 1)->orderBy('sort_order')->orderBy('id')->get();
        $cats     = Category::query()->get(['id', 'label', 'parent_id'])->keyBy('id');
        $currentQ = mb_strtolower(trim((string) $req->query('q', '')));
        $currentCat = $req->routeIs('category.show')
            ? $cats->get((int) $req->route('id'))
            : null;

        $kids = $all->whereNotNull('parent_id')->groupBy('parent_id');
        $out  = [];

        foreach ($all->whereNull('parent_id') as $top) {
            if ($top->type === 'dropdown') {
                $children = [];
                $anyActive = false;

                foreach ($kids->get($top->id, collect()) as $child) {
                    $r = self::resolveItem($child, $cats, $req, $currentCat, $currentQ);
                    if (!$r) {
                        continue;
                    }
                    $anyActive  = $anyActive || $r['active'];
                    $children[] = $r;
                }

                // A dropdown with nothing inside would be an empty box, so it is not shown
                if (!$children) {
                    continue;
                }

                $count = count($children);
                $out[] = [
                    'label'    => $top->label,
                    'url'      => null,
                    'active'   => $anyActive,
                    'children' => $children,
                    'cols'     => $count >= 9 ? 3 : ($count >= 5 ? 2 : 1),
                ];
                continue;
            }

            $r = self::resolveItem($top, $cats, $req, $currentCat, $currentQ);
            if ($r) {
                $out[] = $r + ['children' => [], 'cols' => 1];
            }
        }

        return $out;
    }

    /** One link item -> [label, url, active, new_tab], or null when the link would be broken (e.g. the category was deleted) */
    private static function resolveItem($item, $cats, $req, $currentCat, string $currentQ): ?array
    {
        $url = null;
        $active = false;

        switch ($item->type) {
            case 'home':
                $url    = route('home');
                $active = $req->routeIs('home');
                break;

            case 'category':
                $cat = $item->category_id ? $cats->get((int) $item->category_id) : null;
                if (!$cat) {
                    return null;
                }
                $url    = route('category.show', $cat->id);
                $active = $currentCat && ((int) $currentCat->id === (int) $cat->id
                    || ($cat->parent_id === null && (int) $currentCat->parent_id === (int) $cat->id));
                break;

            case 'search':
                $term = trim((string) $item->search_term);
                if ($term === '') {
                    return null;
                }
                $url    = route('category.index', ['q' => $term]);
                $active = $req->routeIs('category.index') && $currentQ === mb_strtolower($term);
                break;

            case 'url':
                $raw = trim((string) $item->url);
                if ($raw === '' || !NavMenuItem::safeUrl($raw)) {
                    return null;
                }
                if ($raw[0] === '/') {
                    $url    = url($raw);
                    $path   = rtrim((string) parse_url($raw, PHP_URL_PATH), '/');
                    $active = !str_contains($raw, '#') && rtrim('/' . ltrim($req->path(), '/'), '/') === $path;
                } else {
                    $url    = $raw;
                    $active = preg_match('~^https?://~i', $raw) === 1
                        && rtrim($req->url(), '/') === rtrim(strtok($raw, '?#'), '/');
                }
                break;

            default:
                return null;
        }

        return [
            'label'   => $item->label,
            'url'     => $url,
            'active'  => (bool) $active,
            'new_tab' => (bool) $item->new_tab,
        ];
    }
}
