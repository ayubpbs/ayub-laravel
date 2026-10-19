<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** One row per customer phone number (normalized 01XXXXXXXXX). Additive: no existing table is changed. */
    public function up()
    {
        Schema::create('customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->unique();
            $table->string('name', 100)->nullable();
            $table->text('notes')->nullable();                 // internal admin notes
            $table->string('source', 20)->default('admin');    // admin | orders | import
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->string('created_by_name', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('customer_profiles');
    }
};
