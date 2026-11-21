<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Three ways to get an order: delivery (as before), eat there (the restaurant
 * has it ready for a time, for a number of people) and collect (takeaway).
 * An eat-there or collect order can become a delivery: before payment at once,
 * after payment once the delivery fee is paid (the pending change is in
 * "switch").
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('store_orders', function (Blueprint $t) {
            if (!Schema::hasColumn('store_orders', 'fulfilment')) {
                // delivery | dine_in | pickup
                $t->string('fulfilment', 12)->default('delivery')->after('status');
                $t->dateTime('arrive_at')->nullable()->after('fulfilment');
                $t->unsignedTinyInteger('guests')->nullable()->after('arrive_at');
                // Shown by the customer at the restaurant; typed by the staff to hand over.
                $t->string('collect_code', 6)->nullable()->after('guests');
                $t->dateTime('ready_told_at')->nullable()->after('collect_code');
                // A change to delivery waiting for its fee: {fee, quote, drop_address, drop_latitude, drop_longitude, pay_by}
                $t->json('switch')->nullable()->after('ready_told_at');
                $t->uuid('delivery_payment_request_id')->nullable()->after('switch');
            }
        });
        Schema::table('stores', function (Blueprint $t) {
            if (!Schema::hasColumn('stores', 'offers_dine_in')) {
                $t->boolean('offers_dine_in')->default(true)->after('is_open');
                $t->boolean('offers_pickup')->default(true)->after('offers_dine_in');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('store_orders', 'fulfilment')) {
            Schema::table('store_orders', fn(Blueprint $t) => $t->dropColumn(['fulfilment', 'arrive_at', 'guests', 'collect_code', 'ready_told_at', 'switch', 'delivery_payment_request_id']));
        }
        if (Schema::hasColumn('stores', 'offers_dine_in')) {
            Schema::table('stores', fn(Blueprint $t) => $t->dropColumn(['offers_dine_in', 'offers_pickup']));
        }
    }
};
