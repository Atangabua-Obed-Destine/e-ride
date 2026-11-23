<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Room service orders (fulfilment "room"): the hotel's restaurant staff carry the order to a room.
 * Paid by Mobile Money, or put on the room bill in POS Innova (paid_to "room_bill": E-Ride holds nothing).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('store_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('store_orders', 'room_label')) {
                $table->uuid('hotel_id')->nullable();
                $table->uuid('hotel_stay_id')->nullable();
                $table->string('room_label', 20)->nullable();
                $table->boolean('pos_charged_to_room')->nullable();   // POS put it on the bill (false: another payment was taken)
            }
        });
    }

    public function down(): void
    {
    }
};
