<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two ways a store has a say in who delivers:
 *
 *  - "The shop brings it": for a customer close by, the store's own staff walk
 *    the order over (delivered_by = store). No rider, the store's own fee, and
 *    a code the customer gives at the door.
 *  - "My riders": the store keeps a list of E-Ride riders (store_riders). Its
 *    deliveries ring them first, or only them (stores.riders_mode). The trip
 *    carries who it is reserved for, and until when.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $t) {
            if (!Schema::hasColumn('stores', 'own_delivery')) {
                $t->boolean('own_delivery')->default(false)->after('offers_delivery');
                $t->decimal('own_delivery_fee', 10, 2)->default(0)->after('own_delivery');
                // How far the staff go; never more than the admin's maximum. Null = the maximum.
                $t->decimal('own_delivery_km', 4, 1)->nullable()->after('own_delivery_fee');
                // all | first (my riders, then everyone) | only
                $t->string('riders_mode', 8)->default('all')->after('own_delivery_km');
            }
        });

        Schema::table('store_orders', function (Blueprint $t) {
            if (!Schema::hasColumn('store_orders', 'delivered_by')) {
                // rider | store
                $t->string('delivered_by', 8)->default('rider')->after('fulfilment');
                // What an E-Ride rider costs for this order, kept when the store brings it.
                $t->decimal('rider_fee', 10, 2)->nullable()->after('delivery_fee');
                // The store asked for a rider after the customer paid its own (lower) fee: it pays the difference.
                $t->decimal('store_covers', 10, 2)->default(0)->after('rider_fee');
                // E-Ride's part of the store's own delivery fee.
                $t->decimal('own_delivery_share', 10, 2)->default(0)->after('store_covers');
                $t->timestamp('out_at')->nullable()->after('picked_up_at');
                // The store opened this order to every rider (not only its own).
                $t->boolean('riders_open')->default(false)->after('rider_attempts');
            }
        });

        if (!Schema::hasTable('store_riders')) {
            Schema::create('store_riders', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->uuid('store_id');
                $t->uuid('driver_id');
                // active | left (the rider) | removed (the store)
                $t->string('status', 10)->default('active');
                $t->timestamp('ended_at')->nullable();
                $t->timestamps();
                $t->unique(['store_id', 'driver_id']);
                $t->index(['driver_id', 'status']);
            });
        }

        Schema::table('trip_requests', function (Blueprint $t) {
            if (!Schema::hasColumn('trip_requests', 'reserved_for')) {
                // Driver ids who alone see and can accept this request, until reserved_until (null = until opened).
                $t->json('reserved_for')->nullable();
                $t->dateTime('reserved_until')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('trip_requests', 'reserved_for')) {
            Schema::table('trip_requests', fn(Blueprint $t) => $t->dropColumn(['reserved_for', 'reserved_until']));
        }
        Schema::dropIfExists('store_riders');
        if (Schema::hasColumn('store_orders', 'delivered_by')) {
            Schema::table('store_orders', fn(Blueprint $t) => $t->dropColumn(['delivered_by', 'rider_fee', 'store_covers', 'own_delivery_share', 'out_at', 'riders_open']));
        }
        if (Schema::hasColumn('stores', 'own_delivery')) {
            Schema::table('stores', fn(Blueprint $t) => $t->dropColumn(['own_delivery', 'own_delivery_fee', 'own_delivery_km', 'riders_mode']));
        }
    }
};
