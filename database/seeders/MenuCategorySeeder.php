<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Support\NavMenu;
use Illuminate\Database\Seeder;

/**
 * Optional. Creates the main categories and subcategories of the header menu.
 * Run once:  php artisan db:seed --class=MenuCategorySeeder
 *
 * Safe to run again: a category with the same name is reused, never duplicated.
 */
class MenuCategorySeeder extends Seeder
{
    public function run()
    {
        $nextSort = (int) Category::max('sort_order');

        foreach (NavMenu::definition() as $top) {
            if (!empty($top['home'])) {
                continue;
            }

            $parent = $this->find($top['label']);

            if (!$parent) {
                $nextSort++;
                $parent = Category::create(['label' => $top['label'], 'sort_order' => $nextSort]);
                $this->say("Created main category: {$top['label']}");
            } elseif ($parent->parent_id !== null) {
                $this->say("Skipped {$top['label']}: it already is a subcategory of another category.");
                continue;
            }

            foreach ($top['children'] as $i => $child) {
                $label = is_array($child) ? $child[0] : $child;
                $sub   = $this->find($label);

                if (!$sub) {
                    Category::create(['label' => $label, 'parent_id' => $parent->id, 'sort_order' => $i + 1]);
                    $this->say("  Created subcategory: {$label}");
                } elseif ($sub->parent_id === null && $sub->id !== $parent->id && !$sub->children()->exists()) {
                    $sub->update(['parent_id' => $parent->id]);
                    $this->say("  Moved existing category under {$top['label']}: {$label}");
                } else {
                    $this->say("  Kept as it is: {$label}");
                }
            }
        }
    }

    private function find(string $label): ?Category
    {
        return Category::whereRaw('LOWER(label) = ?', [mb_strtolower(trim($label))])->first();
    }

    private function say(string $msg): void
    {
        if ($this->command) {
            $this->command->info($msg);
        }
    }
}
