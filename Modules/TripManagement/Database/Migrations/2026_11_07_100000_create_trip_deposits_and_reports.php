<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Booking deposit: after a driver accepts a ride or parcel, the customer pays
 * a small deposit (set per zone and vehicle category in the fare setup)
 * before the driver sets off. It replaces the cancellation fee.
 *
 * Also the customer's reports about a driver, for the admin to follow up.
 */
return new class extends Migration {
    public function up(): void
    {
        foreach (['trip_fares', 'zone_wise_default_trip_fares'] as $table) {
            if (!Schema::hasColumn($table, 'booking_deposit')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->decimal('booking_deposit', 23, 3)->default(0);
                });
                // Start every configured fare at 500 (the admin changes it per category).
                DB::table($table)->update(['booking_deposit' => 500]);
            }
        }

        if (!Schema::hasTable('trip_deposits')) {
            Schema::create('trip_deposits', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('trip_request_id')->unique();
                $table->foreignUuid('customer_id')->index();
                // Kept here: a customer cancel clears trip_requests.driver_id.
                $table->foreignUuid('driver_id')->nullable()->index();
                $table->foreignUuid('zone_id')->nullable();
                $table->foreignUuid('vehicle_category_id')->nullable();
                $table->decimal('amount', 23, 3);
                // awaiting | paid | applied | forfeited | refunded | expired
                $table->string('status', 20)->index();
                $table->string('payment_method', 40)->nullable();
                $table->foreignUuid('payment_request_id')->nullable();
                $table->timestamp('due_at')->nullable()->index();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('settled_at')->nullable();
                $table->decimal('applied_amount', 23, 3)->default(0);
                $table->decimal('driver_share', 23, 3)->default(0);
                $table->decimal('admin_share', 23, 3)->default(0);
                $table->decimal('refunded_amount', 23, 3)->default(0);
                $table->string('note', 255)->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('trip_reports')) {
            Schema::create('trip_reports', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('trip_request_id')->unique();
                $table->foreignUuid('customer_id')->index();
                $table->foreignUuid('driver_id')->index();
                $table->string('reason', 60);
                $table->string('comment', 1000)->nullable();
                $table->string('trip_status', 30)->nullable();
                $table->string('deposit_status', 20)->nullable();
                // open | reviewed | resolved
                $table->string('status', 20)->default('open')->index();
                $table->string('admin_note', 1000)->nullable();
                $table->foreignUuid('handled_by')->nullable();
                $table->timestamp('handled_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_reports');
        Schema::dropIfExists('trip_deposits');
        foreach (['trip_fares', 'zone_wise_default_trip_fares'] as $table) {
            if (Schema::hasColumn($table, 'booking_deposit')) {
                Schema::table($table, fn(Blueprint $t) => $t->dropColumn('booking_deposit'));
            }
        }
    }
};
