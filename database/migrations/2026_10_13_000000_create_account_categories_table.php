<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Only creates a new table. Nothing existing is changed or removed.
        if (!Schema::hasTable('account_categories')) {
            Schema::create('account_categories', function (Blueprint $table) {
                $table->id();
                $table->string('type', 10);                 // income / expense
                $table->string('name', 60);
                $table->timestamps();

                $table->unique(['type', 'name']);           // no duplicate name inside the same type
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('account_categories');
    }
};
