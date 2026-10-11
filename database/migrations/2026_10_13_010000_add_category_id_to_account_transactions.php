<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Adds one empty (nullable) column. Existing entries stay exactly as they are.
        if (!Schema::hasColumn('account_transactions', 'category_id')) {
            Schema::table('account_transactions', function (Blueprint $table) {
                $table->unsignedBigInteger('category_id')->nullable()->index()->after('note');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('account_transactions', 'category_id')) {
            Schema::table('account_transactions', function (Blueprint $table) {
                $table->dropColumn('category_id');
            });
        }
    }
};
