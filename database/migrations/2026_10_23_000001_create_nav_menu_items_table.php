<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Website header menu, editable from Admin > Website Menu.
 * (The admin page can also create this table with one click, so this migration skips itself if it already exists.)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('nav_menu_items')) {
            return;
        }

        Schema::create('nav_menu_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->string('label', 100);
            $table->string('type', 20);                       // home | dropdown | category | search | url
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('search_term', 150)->nullable();
            $table->string('url', 500)->nullable();
            $table->boolean('new_tab')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nav_menu_items');
    }
};
