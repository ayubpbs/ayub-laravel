<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('admin_notifications', function (Blueprint $table) {
            $table->id();
            // new_order / order_confirmed / courier_sent / delivered / low_stock / return / payment
            $table->string('type', 30);
            $table->string('title');
            $table->string('message', 500)->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('url')->nullable();     // where "Open" takes the admin
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['read_at', 'created_at']);
            $table->index(['type', 'created_at']);
            $table->index(['type', 'product_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('admin_notifications');
    }
};
