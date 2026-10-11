<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('type', 10)->default('percent');            // percent / fixed
            $table->unsignedInteger('value');                          // % or ৳
            $table->unsignedInteger('min_order')->default(0);          // minimum subtotal (0 = none)
            $table->unsignedInteger('max_discount')->nullable();       // cap for percent coupons
            $table->unsignedInteger('usage_limit')->nullable();        // total uses (null = unlimited)
            $table->unsignedInteger('used_count')->default(0);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('note', 150)->nullable();                   // private note for the admin
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('coupons');
    }
};
