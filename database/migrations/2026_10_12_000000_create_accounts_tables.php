<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('accounts')) {
            Schema::create('accounts', function (Blueprint $table) {
                $table->id();
                $table->string('type', 20)->unique();                  // cash / bkash / nagad
                $table->string('name', 60);
                $table->decimal('opening_balance', 12, 2)->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('account_transactions')) {
            Schema::create('account_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('account_id')->constrained('accounts')->cascadeOnDelete();
                $table->string('direction', 3);                        // in / out
                $table->decimal('amount', 12, 2);                      // always positive; "direction" says which way the money moved
                $table->date('txn_date');
                $table->string('note')->nullable();
                $table->string('source', 30)->default('manual');       // manual now; income / expense / transfer later
                $table->timestamps();

                $table->index(['account_id', 'txn_date']);
            });
        }

        foreach (['cash' => 'Cash', 'bkash' => 'bKash', 'nagad' => 'Nagad'] as $type => $name) {
            if (!DB::table('accounts')->where('type', $type)->exists()) {
                DB::table('accounts')->insert([
                    'type' => $type, 'name' => $name, 'opening_balance' => 0,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down()
    {
        Schema::dropIfExists('account_transactions');
        Schema::dropIfExists('accounts');
    }
};
