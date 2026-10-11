<?php

namespace App\Support;

use App\Models\Category;

/**
 * Header navigation menu.
 *
 * - No database changes: the menu itself lives in definition() below.
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
}
