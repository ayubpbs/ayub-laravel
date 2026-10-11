<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('courier')->nullable()->after('status');                 // steadfast
            $table->string('consignment_id')->nullable()->index()->after('courier');
            $table->string('tracking_code')->nullable()->after('consignment_id');
            $table->string('courier_status')->nullable()->index()->after('tracking_code'); // Steadfast delivery_status
            $table->timestamp('courier_sent_at')->nullable()->after('courier_status');
            $table->timestamp('courier_checked_at')->nullable()->after('courier_sent_at');
            $table->timestamp('return_requested_at')->nullable()->after('courier_checked_at');
        });
    }

    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['courier', 'consignment_id', 'tracking_code', 'courier_status', 'courier_sent_at', 'courier_checked_at', 'return_requested_at']);
        });
    }
};
