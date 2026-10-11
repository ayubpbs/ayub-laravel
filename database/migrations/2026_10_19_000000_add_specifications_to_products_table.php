<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Adds one nullable text column: the product specifications as JSON [{"name":"Watt","value":"12W"}, ...].
    // Existing data is not touched.
    public function up()
    {
        if (!Schema::hasColumn('products', 'specifications')) {
            Schema::table('products', function (Blueprint $table) {
                $table->text('specifications')->nullable();
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('products', 'specifications')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('specifications');
            });
        }
    }
};
