<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Room service: a hotel's own restaurant (a Food & Shops store) delivers to the rooms. The hotel is
 * linked to that store; a checked-in guest's stay knows its room number; each room can have a QR card
 * (a short token, never the plain number) that opens the restaurant with the room filled in.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('hotels', function (Blueprint $table) {
            if (!Schema::hasColumn('hotels', 'restaurant_store_id')) {
                $table->uuid('restaurant_store_id')->nullable();   // the hotel's restaurant on E-Ride (brand or a kitchen)
                $table->boolean('pos_room_bill')->default(false);   // POS hotel setting: room service may go on the room bill
            }
        });
        Schema::table('hotel_stays', function (Blueprint $table) {
            if (!Schema::hasColumn('hotel_stays', 'room_label')) {
                $table->string('room_label', 20)->nullable();       // the room number POS gave (or the E-Ride room's number)
            }
        });
        if (!Schema::hasTable('hotel_room_codes')) {
            Schema::create('hotel_room_codes', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('hotel_id')->index();
                $table->string('room_label', 20);
                $table->string('token', 12)->unique();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();
                $table->index(['hotel_id', 'room_label']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_room_codes');
    }
};
