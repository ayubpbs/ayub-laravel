<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Only creates two new tables. Nothing existing is changed or removed.
        if (!Schema::hasTable('account_holdings')) {
            Schema::create('account_holdings', function (Blueprint $table) {
                $table->id();
                $table->string('kind', 20)->index();                 // sanchaypatra / fdr / loan_given / receivable / loan_taken
                $table->string('title', 80);
                $table->string('party', 80)->nullable();             // bank, post office or person
                $table->string('reference_no', 40)->nullable();      // certificate / FDR number
                $table->decimal('principal', 12, 2);
                $table->decimal('interest_rate', 5, 2)->nullable();  // information only
                $table->date('start_date');
                $table->date('maturity_date')->nullable();           // due / maturity date
                $table->string('note')->nullable();
                $table->unsignedBigInteger('account_transaction_id')->nullable(); // money-out / money-in entry made when it was created (optional)
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('account_holding_payments')) {
            Schema::create('account_holding_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('holding_id')->constrained('account_holdings')->cascadeOnDelete();
                $table->decimal('amount', 12, 2);
                $table->date('paid_date');
                $table->string('note')->nullable();
                $table->unsignedBigInteger('account_transaction_id')->nullable();
                $table->timestamps();

                $table->index(['holding_id', 'paid_date']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('account_holding_payments');
        Schema::dropIfExists('account_holdings');
    }
};
