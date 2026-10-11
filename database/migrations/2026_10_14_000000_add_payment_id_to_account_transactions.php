<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Adds one empty (nullable) column. It links an account entry to the customer payment / refund it came from.
        // "unique" = one payment can never be counted twice in the accounts.
        if (!Schema::hasColumn('account_transactions', 'payment_id')) {
            Schema::table('account_transactions', function (Blueprint $table) {
                $table->unsignedBigInteger('payment_id')->nullable()->unique()->after('transfer_ref');
            });
        }

        // Only payments recorded from now on go to the accounts. Older payments are left alone,
        // because the opening balances were probably set with that money already counted.
        if (Schema::hasTable('settings') && Setting::get('acct_link_start') === null) {
            Setting::setMany(['acct_link_start' => now()->format('Y-m-d H:i:s')]);
        }
    }

    public function down()
    {
        if (Schema::hasColumn('account_transactions', 'payment_id')) {
            Schema::table('account_transactions', function (Blueprint $table) {
                $table->dropUnique(['payment_id']);
                $table->dropColumn('payment_id');
            });
        }
    }
};
