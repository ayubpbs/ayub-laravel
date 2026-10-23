<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\NavMenuItem;
use App\Support\Audit;
use App\Support\NavMenu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Admin > Website Menu: edit the header menu of the shop (owner only, see AdminAccess).
 * Until the first item is saved, the shop keeps showing the built-in menu from App\Support\NavMenu::definition().
 */
class NavMenuController extends Controller
{
    public function index()
    {
        $ready = NavMenuItem::tableReady();
        $rows  = collect();
        $custom = false;

        if ($ready) {
            $all    = NavMenuItem::orderBy('sort_order')->orderBy('id')->get();
            $custom = $all->isNotEmpty();
            $kids   = $all->whereNotNull('parent_id')->groupBy('parent_id');
            $tops   = $all->whereNull('parent_id')->values();

            foreach ($tops as $i => $top) {
                $top->setAttribute('depth', 0);
                $top->setAttribute('is_first', $i === 0);
                $top->setAttribute('is_last', $i === $tops->count() - 1);
                $rows->push($top);

                $subs = $kids->get($top->id, collect())->values();
                foreach ($subs as $j => $sub) {
                    $sub->setAttribute('depth', 1);
                    $sub->setAttribute('is_first', $j === 0);
                    $sub->setAttribute('is_last', $j === $subs->count() - 1);
                    $rows->push($sub);
                }
            }
        }

        return view('admin.menu.index', [
            'ready'  => $ready,
            'custom' => $custom,
            'rows'   => $rows,
            'cats'   => Category::query()->get(['id', 'label', 'parent_id'])->keyBy('id'),
        ]);
    }

    public function setup()
    {
        try {
            NavMenuItem::createTable();
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'The menu table could not be created: ' . $e->getMessage() . ' (you can also run "php artisan migrate").');
        }

        return redirect()->route('admin.menu.index')->with('success', 'Menu table created. The website keeps its current menu until you save your first item or load the default menu.');
    }

    public function create(Request $request)
    {
        if (!NavMenuItem::tableReady()) {
            return redirect()->route('admin.menu.index')->with('error', 'Please set up the menu table first.');
        }

        $item   = new NavMenuItem(['type' => 'category', 'is_active' => true, 'new_tab' => false]);
        $parent = (int) $request->query('parent');
        if ($parent > 0 && NavMenuItem::whereKey($parent)->whereNull('parent_id')->where('type', 'dropdown')->exists()) {
            $item->parent_id = $parent;
        }

        return view('admin.menu.form', $this->formData($item));
    }

    public function store(Request $request)
    {
        if (!NavMenuItem::tableReady()) {
            return redirect()->route('admin.menu.index')->with('error', 'Please set up the menu table first.');
        }

        $data = $this->validated($request, null);
        $data['sort_order'] = $this->nextOrder($data['parent_id']);

        $item = NavMenuItem::create($data);
        Audit::log('menu.created', 'nav_menu_item', $item->id, 'Menu item "' . $item->label . '" added', null, $data);

        return redirect()->route('admin.menu.index')->with('success', 'Menu item "' . $item->label . '" added.');
    }

    public function edit($id)
    {
        return view('admin.menu.form', $this->formData(NavMenuItem::findOrFail($id)));
    }

    public function update(Request $request, $id)
    {
        $item = NavMenuItem::findOrFail($id);
        $data = $this->validated($request, $item);

        $oldParent = $item->parent_id === null ? null : (int) $item->parent_id;
        if ($data['parent_id'] !== $oldParent) {
            $data['sort_order'] = $this->nextOrder($data['parent_id']);   // moved to another dropdown: goes to its end
        }

        $before = $item->only(['label', 'type', 'parent_id', 'category_id', 'search_term', 'url', 'new_tab', 'is_active']);
        $item->update($data);
        Audit::log('menu.updated', 'nav_menu_item', $item->id, 'Menu item "' . $item->label . '" changed', $before, $data);

        return redirect()->route('admin.menu.index')->with('success', 'Menu item "' . $item->label . '" saved.');
    }

    public function destroy($id)
    {
        $item  = NavMenuItem::findOrFail($id);
        $label = $item->label;

        DB::transaction(function () use ($item) {
            NavMenuItem::where('parent_id', $item->id)->delete();
            $item->delete();
        });

        Audit::log('menu.deleted', 'nav_menu_item', (int) $id, 'Menu item "' . $label . '" deleted (with its sub-items)');

        return redirect()->route('admin.menu.index')->with('success', 'Menu item "' . $label . '" deleted.');
    }

    /** Moves an item one place up or down among the items next to it (same level, same dropdown) */
    public function move(Request $request, $id)
    {
        $item = NavMenuItem::findOrFail($id);
        $up   = $request->input('dir') === 'up';

        DB::transaction(function () use ($item, $up) {
            $siblings = NavMenuItem::where(function ($q) use ($item) {
                    $item->parent_id === null ? $q->whereNull('parent_id') : $q->where('parent_id', $item->parent_id);
                })
                ->orderBy('sort_order')->orderBy('id')->lockForUpdate()->get()->values();

            $ids = $siblings->pluck('id')->map(fn ($v) => (int) $v)->all();
            $i   = array_search((int) $item->id, $ids, true);
            $j   = $up ? $i - 1 : $i + 1;

            if ($i === false || $j < 0 || $j >= count($ids)) {
                return;
            }

            [$ids[$i], $ids[$j]] = [$ids[$j], $ids[$i]];
            foreach ($ids as $pos => $sid) {
                NavMenuItem::whereKey($sid)->update(['sort_order' => $pos + 1]);
            }
        });

        return back();
    }

    /** Show / hide an item on the website without deleting it */
    public function toggle($id)
    {
        $item = NavMenuItem::findOrFail($id);
        $item->update(['is_active' => !$item->is_active]);

        return back()->with('success', '"' . $item->label . '" is now ' . ($item->is_active ? 'visible' : 'hidden') . ' on the website.');
    }

    /** Copies the built-in menu into the editor, so the admin can start from what the website shows now */
    public function import()
    {
        DB::transaction(function () {
            NavMenuItem::query()->delete();

            $cats = Category::query()->get(['id', 'label', 'parent_id'])->keyBy(fn ($c) => mb_strtolower(trim($c->label)));
            $top  = 0;

            foreach (NavMenu::definition() as $def) {
                $top++;

                if (!empty($def['home'])) {
                    NavMenuItem::create(['label' => $def['label'], 'type' => 'home', 'sort_order' => $top, 'is_active' => true]);
                    continue;
                }

                $parent = NavMenuItem::create(['label' => $def['label'], 'type' => 'dropdown', 'sort_order' => $top, 'is_active' => true]);
                $pos    = 0;

                // Same as the built-in menu: a main category with the menu's name gets an "All ..." link on top
                $topCat = $cats->get(mb_strtolower(trim($def['label'])));
                if ($topCat && $topCat->parent_id === null) {
                    NavMenuItem::create([
                        'parent_id' => $parent->id, 'label' => 'All ' . $def['label'], 'type' => 'category',
                        'category_id' => $topCat->id, 'sort_order' => ++$pos, 'is_active' => true,
                    ]);
                }

                foreach ($def['children'] as $child) {
                    [$label, $q] = is_array($child) ? $child : [$child, $child];
                    $cat = $cats->get(mb_strtolower(trim($label)));

                    NavMenuItem::create([
                        'parent_id'   => $parent->id,
                        'label'       => $label,
                        'type'        => $cat ? 'category' : 'search',
                        'category_id' => $cat ? $cat->id : null,
                        'search_term' => $cat ? null : $q,
                        'sort_order'  => ++$pos,
                        'is_active'   => true,
                    ]);
                }
            }
        });

        Audit::log('menu.imported', 'nav_menu_item', null, 'Built-in menu loaded into the menu editor');

        return redirect()->route('admin.menu.index')->with('success', 'The default menu was loaded. You can edit it now.');
    }

    /** Deletes the saved menu: the website goes back to its built-in menu */
    public function reset()
    {
        NavMenuItem::query()->delete();
        Audit::log('menu.reset', 'nav_menu_item', null, 'Saved menu deleted, the default menu is shown again');

        return redirect()->route('admin.menu.index')->with('success', 'The saved menu was deleted. The website shows the default menu again.');
    }

    // ----- Helpers -----

    private function formData(NavMenuItem $item): array
    {
        return [
            'item'       => $item,
            'dropdowns'  => NavMenuItem::whereNull('parent_id')->where('type', 'dropdown')->orderBy('sort_order')->orderBy('id')->get(),
            'categories' => Category::flatTree(Category::orderBy('sort_order')->orderBy('id')->get()),
            'hasKids'    => $item->exists && NavMenuItem::where('parent_id', $item->id)->exists(),
        ];
    }

    private function nextOrder(?int $parentId): int
    {
        $q = NavMenuItem::query();
        $parentId === null ? $q->whereNull('parent_id') : $q->where('parent_id', $parentId);

        return (int) $q->max('sort_order') + 1;
    }

    /** Checks the form and returns the values to save (fields that do not belong to the chosen type are cleared) */
    private function validated(Request $request, ?NavMenuItem $current): array
    {
        $parentRaw = $request->input('parent_id');
        $parentId  = ($parentRaw === null || $parentRaw === '') ? null : (int) $parentRaw;
        $allowed   = $parentId === null ? array_keys(NavMenuItem::types()) : array_keys(NavMenuItem::subTypes());

        $data = $request->validate([
            'label'       => 'required|string|max:80',
            'type'        => ['required', Rule::in($allowed)],
            'parent_id'   => 'nullable|integer',
            'category_id' => ['nullable', 'required_if:type,category', 'integer', Rule::exists('categories', 'id')],
            'search_term' => 'nullable|required_if:type,search|string|max:150',
            'url'         => 'nullable|required_if:type,url|string|max:500',
        ], [
            'label.required'          => 'Please enter the menu text.',
            'type.in'                 => 'This type cannot be used here. Items inside a dropdown must be a category, a product search or a custom link.',
            'category_id.required_if' => 'Please choose a category.',
            'category_id.exists'      => 'This category does not exist.',
            'search_term.required_if' => 'Please enter the search word.',
            'url.required_if'         => 'Please enter the link.',
        ]);

        if ($parentId !== null) {
            $parentOk = NavMenuItem::whereKey($parentId)->whereNull('parent_id')->where('type', 'dropdown')->exists();
            if (!$parentOk || ($current && (int) $current->id === $parentId)) {
                throw ValidationException::withMessages(['parent_id' => 'Please choose a valid dropdown.']);
            }
        }

        if ($current && NavMenuItem::where('parent_id', $current->id)->exists()) {
            if ($parentId !== null) {
                throw ValidationException::withMessages(['parent_id' => 'This dropdown has sub-items, so it cannot be placed inside another dropdown.']);
            }
            if ($data['type'] !== 'dropdown') {
                throw ValidationException::withMessages(['type' => 'This item has sub-items. Delete or move them first to change its type.']);
            }
        }

        $type = $data['type'];
        $url  = $type === 'url' ? trim((string) $data['url']) : null;
        if ($type === 'url' && !NavMenuItem::safeUrl($url)) {
            throw ValidationException::withMessages(['url' => 'Please enter a full link starting with https://, a path on this site like /about, a #section, mailto: or tel:.']);
        }

        return [
            'parent_id'   => $parentId,
            'label'       => trim($data['label']),
            'type'        => $type,
            'category_id' => $type === 'category' ? (int) $data['category_id'] : null,
            'search_term' => $type === 'search' ? trim((string) $data['search_term']) : null,
            'url'         => $url,
            'new_tab'     => !in_array($type, ['dropdown'], true) && $request->boolean('new_tab'),
            'is_active'   => $request->boolean('is_active'),
        ];
    }
}
