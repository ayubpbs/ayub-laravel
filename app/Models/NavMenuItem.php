<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One item of the website header menu (managed in Admin > Website Menu).
 * Two levels only: a top-level item (link or dropdown) and, inside a dropdown, its sub-items.
 */
class NavMenuItem extends Model
{
    protected $table = 'nav_menu_items';

    protected $fillable = ['parent_id', 'label', 'type', 'category_id', 'search_term', 'url', 'new_tab', 'is_active', 'sort_order'];

    protected $casts = [
        'new_tab'   => 'boolean',
        'is_active' => 'boolean',
    ];

    private static ?bool $ready = null;

    /** False until the table exists, so the shop keeps using the built-in menu */
    public static function tableReady(): bool
    {
        if (self::$ready === null) {
            try {
                self::$ready = Schema::hasTable('nav_menu_items');
            } catch (\Throwable $e) {
                self::$ready = false;
            }
        }

        return self::$ready;
    }

    /** Creates the table (same as the migration). Safe to call twice. */
    public static function createTable(): void
    {
        if (!Schema::hasTable('nav_menu_items')) {
            Schema::create('nav_menu_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('parent_id')->nullable()->index();
                $table->string('label', 100);
                $table->string('type', 20);
                $table->unsignedBigInteger('category_id')->nullable();
                $table->string('search_term', 150)->nullable();
                $table->string('url', 500)->nullable();
                $table->boolean('new_tab')->default(false);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }
        self::$ready = null;
    }

    /** True when the admin has saved at least one item: then this menu replaces the built-in one */
    public static function hasItems(): bool
    {
        try {
            return self::tableReady() && static::query()->exists();
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function types(): array
    {
        return [
            'home'     => 'Home page',
            'dropdown' => 'Dropdown (heading with a sub-menu)',
            'category' => 'Category page',
            'search'   => 'Product search',
            'url'      => 'Custom link (any URL)',
        ];
    }

    /** What a sub-item inside a dropdown can be */
    public static function subTypes(): array
    {
        return array_intersect_key(self::types(), array_flip(['category', 'search', 'url']));
    }

    /** Only safe link targets are allowed: http(s), a path on this site, a #section, mailto: and tel: (never javascript:) */
    public static function safeUrl(string $url): bool
    {
        return (bool) preg_match('~^(https?://[^\s]+|/[^\s]*|#[^\s]*|mailto:[^\s]+|tel:[+0-9 \-()]+)$~i', trim($url));
    }

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    public function typeLabel(): string
    {
        return self::types()[$this->type] ?? ucfirst((string) $this->type);
    }
}
