<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Saved delivery addresses of a customer. Orders keep their own copy of the address,
     * so changing or deleting a saved address never changes an order.
     */
    public function up()
    {
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_profile_id')->constrained('customer_profiles');
            $table->string('recipient_name', 100);
            $table->string('phone', 20);
            $table->string('district', 60);
            $table->string('upazila', 100);
            $table->string('city', 100);
            $table->string('address', 500);
            $table->string('landmark', 255)->nullable();   // landmark / delivery instructions
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['customer_profile_id', 'is_default']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('customer_addresses');
    }
};
