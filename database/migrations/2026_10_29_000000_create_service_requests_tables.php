<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Phase 5: exchange / wrong-product / defective claims ("service requests").
     * Additive only: four new tables and one nullable column on orders and on payments.
     * Nothing existing is changed or removed.
     */
    public function up()
    {
        if (!Schema::hasTable('service_requests')) {
            Schema::create('service_requests', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('order_id')->index();          // the ORIGINAL order (never edited)
                $table->string('customer_name', 100)->nullable();          // copied from the order when the claim is opened
                $table->string('customer_phone', 20)->nullable()->index();
                $table->string('type', 20);                                // wrong_product | defective | damaged | exchange | other
                $table->string('status', 20)->default('open')->index();    // open | approved | rejected | resolved
                $table->text('reason')->nullable();
                $table->boolean('return_required')->default(true);         // must the customer send the goods back?
                $table->string('resolution', 20)->nullable();              // replacement | exchange | refund (chosen at approval)
                $table->text('decision_note')->nullable();
                $table->unsignedBigInteger('decided_by_id')->nullable();
                $table->string('decided_by_name', 100)->nullable();
                $table->timestamp('decided_at')->nullable();
                $table->unsignedBigInteger('replacement_order_id')->nullable()->index(); // the linked new order (replacement / exchange)
                $table->unsignedInteger('exchange_credit')->default(0);    // value of the returned goods taken off the exchange order
                $table->unsignedBigInteger('opened_by_id')->nullable();
                $table->string('opened_by_name', 100)->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->string('closed_by_name', 100)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('service_request_items')) {
            Schema::create('service_request_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('service_request_id')->index();
                $table->unsignedBigInteger('order_item_id')->nullable()->index();
                $table->unsignedBigInteger('product_id')->nullable();          // the product that was ordered
                $table->unsignedBigInteger('received_product_id')->nullable(); // wrong product: what the customer actually got (stock goes here)
                $table->string('title');
                $table->unsignedInteger('price');                              // price paid per unit (from the original order)
                $table->unsignedInteger('quantity');
                // Inspection of the goods that came back. The three numbers must add up to "quantity".
                $table->unsignedInteger('qty_restock')->default(0);
                $table->unsignedInteger('qty_supplier')->default(0);
                $table->unsignedInteger('qty_scrap')->default(0);
                $table->timestamp('inspected_at')->nullable();
                $table->string('inspected_by_name', 100)->nullable();
                $table->string('inspection_note', 255)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('service_request_files')) {
            Schema::create('service_request_files', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('service_request_id')->index();
                $table->string('path');                                    // inside storage/app (private), never under public/
                $table->string('original_name', 190)->nullable();
                $table->string('mime', 100);
                $table->unsignedBigInteger('size')->default(0);
                $table->string('kind', 10);                                // image | video
                $table->string('note', 255)->nullable();
                $table->string('uploaded_by_name', 100)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('service_request_shipments')) {
            Schema::create('service_request_shipments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('service_request_id')->index();
                $table->string('direction', 10);                           // inbound (customer to us) | outbound
                $table->string('courier', 60)->nullable();
                $table->string('tracking_code', 100)->nullable();
                $table->string('status', 15)->default('pending');          // pending | in_transit | received | failed
                $table->timestamp('shipped_at')->nullable();
                $table->timestamp('received_at')->nullable();
                $table->string('note', 255)->nullable();
                $table->string('created_by_name', 100)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasColumn('orders', 'service_request_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->unsignedBigInteger('service_request_id')->nullable()->index(); // set on replacement / exchange orders
            });
        }

        if (!Schema::hasColumn('payments', 'service_request_id')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->unsignedBigInteger('service_request_id')->nullable()->index(); // set on refunds given for a claim
            });
        }
    }

    public function down()
    {
        foreach (['orders', 'payments'] as $t) {
            if (Schema::hasColumn($t, 'service_request_id')) {
                Schema::table($t, function (Blueprint $table) {
                    $table->dropIndex(['service_request_id']);
                });
                Schema::table($t, function (Blueprint $table) {
                    $table->dropColumn('service_request_id');
                });
            }
        }

        foreach (['service_request_shipments', 'service_request_files', 'service_request_items', 'service_requests'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
