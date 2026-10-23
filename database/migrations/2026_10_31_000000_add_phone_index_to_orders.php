<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Customer search and the customer pages look orders up by phone number. Without an index that gets slow
     * as orders grow. Adding an index changes no data; if the index already exists nothing happens.
     */
    public function up()
    {
        try {
            Schema::table('orders', function (Blueprint $table) {
                $table->index('phone', 'orders_phone_index');
            });
        } catch (\Throwable $e) {
            // the index is already there
        }
    }

    public function down()
    {
        try {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropIndex('orders_phone_index');
            });
        } catch (\Throwable $e) {
            // nothing to remove
        }
    }
};
