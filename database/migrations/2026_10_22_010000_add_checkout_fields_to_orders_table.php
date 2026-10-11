<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('district', 60)->nullable()->after('phone');
            $table->string('upazila', 100)->nullable()->after('district');
            $table->string('city', 100)->nullable()->after('upazila');
            $table->string('coupon_code', 30)->nullable()->after('subtotal');
            $table->unsignedInteger('discount')->default(0)->after('coupon_code');
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['district', 'upazila', 'city', 'coupon_code', 'discount']);
        });
    }
};
