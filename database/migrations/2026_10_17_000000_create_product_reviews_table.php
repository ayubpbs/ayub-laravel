<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Customer reviews. Only creates a new table; products.rating / products.reviews are kept
    // in step by the app when a review is approved, rejected or deleted.
    public function up()
    {
        if (!Schema::hasTable('product_reviews')) {
            Schema::create('product_reviews', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('product_id')->index();
                $table->string('name', 80);
                $table->unsignedTinyInteger('rating');                       // 1 - 5
                $table->text('comment')->nullable();
                $table->string('status', 10)->default('pending')->index();   // pending / approved / rejected
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('product_reviews');
    }
};
