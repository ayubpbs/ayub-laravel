<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            // 'cod' (cash on delivery) or 'bkash'. Old orders count as cash on delivery.
            $table->string('payment_method', 20)->default('cod')->after('total');
            // what the customer says they sent, and the optional transaction ID
            $table->unsignedInteger('bkash_amount')->nullable()->after('payment_method');
            $table->string('bkash_trx_id', 40)->nullable()->after('bkash_amount');
            // set when the admin verifies it: the id of the payment row that was added (shows on the invoice)
            $table->unsignedBigInteger('bkash_payment_id')->nullable()->after('bkash_trx_id');
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'bkash_amount', 'bkash_trx_id', 'bkash_payment_id']);
        });
    }
};
