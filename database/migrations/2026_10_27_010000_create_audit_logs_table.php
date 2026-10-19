<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Who did what: written once, never edited. Additive: no existing table is changed. */
    public function up()
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_id')->nullable();      // staff id (null = owner)
            $table->string('actor_name', 100)->nullable();           // "Owner" or the staff name
            $table->string('action', 60);                            // e.g. address.created
            $table->string('subject_type', 60);                      // e.g. customer_address
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('customer_phone', 20)->nullable();        // lets a customer page list its own history
            $table->string('summary', 255);
            $table->text('old_values')->nullable();                  // JSON
            $table->text('new_values')->nullable();                  // JSON
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['subject_type', 'subject_id']);
            $table->index('customer_phone');
            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('audit_logs');
    }
};
