<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Adds one nullable text column: YouTube links, one per line. Existing data is not touched.
    public function up()
    {
        if (!Schema::hasColumn('products', 'video_urls')) {
            Schema::table('products', function (Blueprint $table) {
                $table->text('video_urls')->nullable();
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('products', 'video_urls')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('video_urls');
            });
        }
    }
};
