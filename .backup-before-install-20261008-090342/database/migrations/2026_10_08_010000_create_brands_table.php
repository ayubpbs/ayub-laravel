<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('logo')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('brand_id')->nullable()->index()->after('category_id');
        });

        // Existing products only have a brand name typed in. Turn each distinct name into a real brand and link it.
        $names = DB::table('products')->whereNotNull('brand')->where('brand', '!=', '')->distinct()->pluck('brand');
        foreach ($names as $i => $name) {
            $name = trim($name);
            $id = DB::table('brands')->where('name', $name)->value('id')
               ?? DB::table('brands')->insertGetId(['name' => $name, 'sort_order' => $i + 1, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('products')->where('brand', $name)->update(['brand_id' => $id]);
        }
    }

    public function down()
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('brand_id');
        });
        Schema::dropIfExists('brands');
    }
};
