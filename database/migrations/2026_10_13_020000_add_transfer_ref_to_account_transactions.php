<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Adds one empty (nullable) column. A transfer is two entries (out + in) that share the same code.
        if (!Schema::hasColumn('account_transactions', 'transfer_ref')) {
            Schema::table('account_transactions', function (Blueprint $table) {
                $table->string('transfer_ref', 36)->nullable()->index()->after('source');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('account_transactions', 'transfer_ref')) {
            Schema::table('account_transactions', function (Blueprint $table) {
                $table->dropColumn('transfer_ref');
            });
        }
    }
};
