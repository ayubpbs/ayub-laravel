<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Staff accounts. The owner is not in this table: the owner signs in with ADMIN_USER / ADMIN_PASSWORD from .env as before.
        Schema::create('staff_members', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('username', 50)->unique();
            $table->string('password');
            $table->text('permissions')->nullable();      // JSON list of the tasks this person may use
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        // Who sent the order to the courier. The name is kept so the history stays even if a staff account is deleted.
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('courier_sent_by')->nullable();
            $table->string('courier_sent_by_name', 100)->nullable();
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['courier_sent_by', 'courier_sent_by_name']);
        });

        Schema::dropIfExists('staff_members');
    }
};
