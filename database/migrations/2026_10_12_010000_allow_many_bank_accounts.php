<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('accounts', 'account_no')) {
            Schema::table('accounts', function (Blueprint $table) {
                $table->string('account_no', 40)->nullable()->after('name');
            });
        }

        // "type" was unique (one row per type). Banks need many rows with type = bank.
        try {
            Schema::table('accounts', function (Blueprint $table) {
                $table->dropUnique('accounts_type_unique');
            });
        } catch (\Throwable $e) {
            // already dropped
        }

        try {
            Schema::table('accounts', function (Blueprint $table) {
                $table->index('type');
            });
        } catch (\Throwable $e) {
            // index already there
        }
    }

    public function down()
    {
        if (Schema::hasColumn('accounts', 'account_no')) {
            Schema::table('accounts', function (Blueprint $table) {
                $table->dropColumn('account_no');
            });
        }
    }
};
