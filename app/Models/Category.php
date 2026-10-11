<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['parent_id', 'label', 'icon', 'image', 'sort_order'];

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Turns a list of categories into one flat list ordered as a tree:
     * each main category followed by its subcategories. Every item gets a "depth" (0 or 1).
     */
    public static function flatTree(Collection $all): Collection
    {
        $ids      = $all->pluck('id')->all();
        $children = $all->filter(function ($c) use ($ids) {
            return $c->parent_id !== null && in_array((int) $c->parent_id, $ids, true);
        })->groupBy('parent_id');

        $out = collect();

        foreach ($all as $c) {
            // A category is "main" if it has no parent (or its parent no longer exists)
            if ($c->parent_id !== null && in_array((int) $c->parent_id, $ids, true)) {
                continue;
            }
            $c->setAttribute('depth', 0);
            $out->push($c);

            foreach ($children->get($c->id, collect()) as $child) {
                $child->setAttribute('depth', 1);
                $out->push($child);
            }
        }

        return $out;
    }
}
