<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 4: orders created by an admin. Additive only: every column is nullable (or has a default),
     * so existing orders and the public checkout keep working exactly as before.
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'created_by_id')) {
                $table->unsignedBigInteger('created_by_id')->nullable();       // staff id (null = owner or customer)
            }
            if (!Schema::hasColumn('orders', 'created_by_name')) {
                $table->string('created_by_name', 100)->nullable();            // "Owner" or the staff name; null = placed by the customer
            }
            if (!Schema::hasColumn('orders', 'source_order_id')) {
                $table->unsignedBigInteger('source_order_id')->nullable();     // the old order this one was copied from (Reorder)
            }
            if (!Schema::hasColumn('orders', 'client_token')) {
                $table->string('client_token', 64)->nullable()->unique();      // one-time form token: a double click cannot create two orders
            }
            if (!Schema::hasColumn('orders', 'address_id')) {
                $table->unsignedBigInteger('address_id')->nullable();          // saved address it was copied from (information only)
            }
            if (!Schema::hasColumn('orders', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable();                 // when the order became "delivered" (used by warranty later)
            }
            if (!Schema::hasColumn('orders', 'order_type')) {
                $table->string('order_type', 20)->default('normal');           // normal now; replacement / exchange come in a later phase
            }
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'client_token')) {
                $table->dropUnique(['client_token']);
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            foreach (['created_by_id', 'created_by_name', 'source_order_id', 'client_token', 'address_id', 'delivered_at', 'order_type'] as $col) {
                if (Schema::hasColumn('orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
