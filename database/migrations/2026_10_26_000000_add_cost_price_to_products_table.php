<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Adds one empty (nullable) column: what one unit cost you to buy. Existing products keep working; they simply have no cost yet.
    public function up()
    {
        if (!Schema::hasColumn('products', 'cost_price')) {
            Schema::table('products', function (Blueprint $table) {
                $table->unsignedInteger('cost_price')->nullable();
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('products', 'cost_price')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('cost_price');
            });
        }
    }
};
