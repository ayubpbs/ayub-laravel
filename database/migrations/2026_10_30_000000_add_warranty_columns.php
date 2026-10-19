<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 6: warranty. Additive only: every new column is nullable, so existing products, orders, order lines
     * and claims keep working exactly as before.
     *
     *  products         the product's own warranty policy (empty = use the shop default from Settings)
     *  order_items      a snapshot of the policy at the time of sale (empty on old lines: filled in when a claim is opened)
     *  service_requests the policy used for a warranty claim + start / expiry + serials + inspection of the returned unit
     */
    private array $columns = [
        'products' => [
            ['warranty_type', 'string', 20],
            ['warranty_days', 'unsignedInteger', null],
            ['warranty_terms', 'text', null],
        ],
        'order_items' => [
            ['warranty_type', 'string', 20],
            ['warranty_days', 'unsignedInteger', null],
            ['warranty_terms', 'text', null],
            ['warranty_source', 'string', 20],     // product | shop | backfilled
        ],
        'service_requests' => [
            ['warranty_type', 'string', 20],
            ['warranty_days', 'unsignedInteger', null],
            ['warranty_terms', 'text', null],
            ['warranty_source', 'string', 20],
            ['warranty_start_at', 'timestamp', null],
            ['warranty_start_source', 'string', 20],   // delivered | admin
            ['warranty_expires_at', 'timestamp', null],
            ['warranty_state', 'string', 20],          // in_warranty | expired | not_covered
            ['serial_number', 'string', 100],
            ['replacement_serial', 'string', 100],
            ['symptoms', 'text', null],
            ['received_condition', 'text', null],
            ['inspection_result', 'string', 20],
            ['inspection_note', 'text', null],
            ['inspected_at', 'timestamp', null],
            ['inspected_by_name', 'string', 100],
        ],
    ];

    public function up()
    {
        foreach ($this->columns as $table => $cols) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($table, $cols) {
                foreach ($cols as [$name, $type, $len]) {
                    if (Schema::hasColumn($table, $name)) {
                        continue;
                    }
                    $col = $len ? $t->{$type}($name, $len) : $t->{$type}($name);
                    $col->nullable();
                }
            });
        }
    }

    public function down()
    {
        foreach ($this->columns as $table => $cols) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            foreach ($cols as [$name]) {
                if (Schema::hasColumn($table, $name)) {
                    Schema::table($table, function (Blueprint $t) use ($name) {
                        $t->dropColumn($name);
                    });
                }
            }
        }
    }
};
