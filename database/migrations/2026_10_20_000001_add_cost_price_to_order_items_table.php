<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saves the buying price (cost) of one unit on every order line at the time of sale,
 * so the Profit / Loss report stays correct even if the product's cost price changes later.
 *
 * Non-destructive: only adds one nullable column. Existing orders keep NULL
 * (the report falls back to the product's current cost price for those).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('order_items') && !Schema::hasColumn('order_items', 'cost_price')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->unsignedInteger('cost_price')->nullable()->after('price');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('order_items', 'cost_price')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropColumn('cost_price');
            });
        }
    }
};
