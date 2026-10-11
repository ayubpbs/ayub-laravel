<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('payment');          // payment / refund
            $table->string('method');                            // cod / cash / bkash / nagad / rocket / bank / other
            $table->unsignedInteger('amount');                   // always positive; "type" says which way the money moved
            $table->string('trx_id')->nullable()->unique();      // bKash / Nagad / bank transaction id (never used twice)
            $table->string('sender_number', 20)->nullable();
            $table->string('note')->nullable();
            $table->timestamp('paid_at')->useCurrent();
            $table->timestamps();

            $table->index(['type', 'paid_at']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('payments');
    }
};
