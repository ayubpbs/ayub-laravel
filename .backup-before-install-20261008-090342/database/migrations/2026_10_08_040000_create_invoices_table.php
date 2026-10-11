<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();                                         // the invoice number is built from this id
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamp('issued_at')->useCurrent();
            $table->string('note')->nullable();                   // optional note printed on this invoice
            $table->timestamps();
        });

        // Orders that are already confirmed or beyond get an invoice now, oldest first, so numbers follow order dates
        $orders = DB::table('orders')->whereIn('status', ['confirmed', 'courier_sent', 'delivered'])->orderBy('id')->pluck('created_at', 'id');
        foreach ($orders as $orderId => $createdAt) {
            DB::table('invoices')->insert([
                'order_id'   => $orderId,
                'issued_at'  => $createdAt ?: now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down()
    {
        Schema::dropIfExists('invoices');
    }
};
