<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The old "shipped" status is now called "courier_sent".
     * ("returned" is a new status and needs no schema change: the column is a plain string.)
     */
    public function up()
    {
        DB::table('orders')->where('status', 'shipped')->update(['status' => 'courier_sent']);
    }

    public function down()
    {
        DB::table('orders')->where('status', 'courier_sent')->update(['status' => 'shipped']);
        DB::table('orders')->where('status', 'returned')->update(['status' => 'delivered']);
    }
};
