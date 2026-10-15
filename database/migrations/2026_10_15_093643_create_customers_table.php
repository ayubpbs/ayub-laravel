<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('phone', 30)->unique();
            $table->string('city')->nullable();
            $table->string('zone')->nullable();
            $table->string('area')->nullable();
            $table->text('address')->nullable();
            $table->unsignedInteger('total_orders')->default(0);
            $table->timestamp('first_order')->nullable();
            $table->timestamp('last_order')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('customers');
    }
};
