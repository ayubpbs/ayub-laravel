<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Adds one nullable, unique column: the product SKU. Existing products simply have no SKU yet.
    public function up()
    {
        if (!Schema::hasColumn('products', 'sku')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('sku', 60)->nullable()->unique();
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('products', 'sku')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropUnique(['sku']);
                $table->dropColumn('sku');
            });
        }
    }
};
