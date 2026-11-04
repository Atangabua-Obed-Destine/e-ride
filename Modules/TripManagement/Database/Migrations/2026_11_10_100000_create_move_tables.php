<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E-Ride Move: trucks booked like rides, with the price agreed in person.
 * A move is a ride_request trip with is_move set; it only ever charges the
 * booking deposit. The directory lists truck owners customers can call when
 * no truck is available in the app.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('trip_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('trip_requests', 'is_move')) {
                $table->boolean('is_move')->default(false)->index();
            }
            if (!Schema::hasColumn('trip_requests', 'move_photos')) {
                $table->json('move_photos')->nullable();
            }
        });

        if (!Schema::hasTable('move_directory_listings')) {
            Schema::create('move_directory_listings', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('zone_id')->index();
                $table->foreignUuid('vehicle_category_id')->index();
                // Set when the owner is also an E-Ride driver.
                $table->foreignUuid('user_id')->nullable()->index();
                $table->string('owner_name');
                $table->string('phone', 30);
                $table->string('whatsapp', 30)->nullable();
                $table->string('areas')->nullable();
                $table->string('photo')->nullable();
                $table->string('note', 500)->nullable();
                // When the owner agreed to have their number shown to customers.
                $table->timestamp('consent_at')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('move_directory_contacts')) {
            Schema::create('move_directory_contacts', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('listing_id')->index();
                $table->foreignUuid('customer_id')->nullable()->index();
                $table->string('channel', 20);
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('move_directory_contacts');
        Schema::dropIfExists('move_directory_listings');
        Schema::table('trip_requests', function (Blueprint $table) {
            foreach (['is_move', 'move_photos'] as $column) {
                if (Schema::hasColumn('trip_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
