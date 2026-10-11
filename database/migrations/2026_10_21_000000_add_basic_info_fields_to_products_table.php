<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Adds slug (unique), short_description and product_type to products, gives every existing product a slug,
    // and (MySQL / MariaDB only) makes the description column MEDIUMTEXT so long formatted HTML fits.
    // Nothing existing is removed.
    public function up()
    {
        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'slug')) {
                $table->string('slug', 190)->nullable()->unique();
            }
            if (!Schema::hasColumn('products', 'short_description')) {
                $table->text('short_description')->nullable();
            }
            if (!Schema::hasColumn('products', 'product_type')) {
                $table->string('product_type', 20)->default('simple');
            }
        });

        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE products MODIFY description MEDIUMTEXT NULL');
        }

        // Slug for every product that has none: from the product name (Bengali letters are kept), made unique.
        $used = DB::table('products')->whereNotNull('slug')->pluck('slug')->map(function ($s) { return mb_strtolower($s); })->all();
        $used = array_flip($used);

        foreach (DB::table('products')->whereNull('slug')->orderBy('id')->get(['id', 'title']) as $p) {
            $base = trim((string) preg_replace('/[^\p{L}\p{M}\p{N}]+/u', '-', mb_strtolower((string) $p->title)), '-');
            if ($base === '' || ctype_digit($base)) {
                $base = 'product' . ($base !== '' ? '-' . $base : '');
            }
            $base = mb_substr($base, 0, 150);

            $slug = $base;
            for ($n = 2; isset($used[mb_strtolower($slug)]); $n++) {
                $slug = $base . '-' . $n;
            }
            $used[mb_strtolower($slug)] = true;

            DB::table('products')->where('id', $p->id)->update(['slug' => $slug]);
        }
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'slug')) {
                $table->dropUnique(['slug']);
                $table->dropColumn('slug');
            }
            if (Schema::hasColumn('products', 'short_description')) {
                $table->dropColumn('short_description');
            }
            if (Schema::hasColumn('products', 'product_type')) {
                $table->dropColumn('product_type');
            }
        });
    }
};
