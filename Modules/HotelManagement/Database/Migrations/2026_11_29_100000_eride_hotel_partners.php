<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E-Ride hotel partners. A partner hotel is a place E-Ride already verified (places, explore
 * category "stay") that also runs the hotel portal: its room types and room numbers, which rooms
 * are free, kept, occupied or being cleaned, and the guests' questions. Free for hotels, and no
 * money moves in E-Ride: guests ask, the reception may keep a room until a time, guests ride there
 * and pay the hotel as usual.
 *
 * Every timestamp column is nullable: on MariaDB the first NOT NULL timestamp of a table gets
 * ON UPDATE CURRENT_TIMESTAMP and would change by itself on every update.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('hotels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // The verified listing: photos, address, location, amenities, rating link.
            $table->uuid('place_id')->unique();
            $table->string('name', 120);
            // setup (portal open, not shown as a partner yet) | live | paused
            $table->string('status', 12)->default('setup');
            $table->string('pause_note', 300)->nullable();
            // The number behind the Call button.
            $table->string('reception_phone', 30)->nullable();
            $table->string('check_in_from', 5)->default('12:00');
            $table->string('check_out_by', 5)->default('11:00');
            // Reception answers in the app day and night, or between two hours.
            $table->boolean('answer_always')->default(true);
            $table->string('answer_from', 5)->nullable();
            $table->string('answer_until', 5)->nullable();
            // Longest a room is kept for a guest arriving the same day.
            $table->unsignedTinyInteger('keep_max_hours')->default(6);
            // Last time the reception touched the rooms: older than the admin's limit, guests see no counts.
            $table->timestamp('board_touched_at')->nullable();
            $table->uuid('verified_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index('status');
        });

        Schema::create('hotel_staff', function (Blueprint $table) {
            $table->id();
            $table->uuid('hotel_id');
            $table->uuid('user_id')->unique();
            // manager | reception
            $table->string('role', 12);
            $table->boolean('active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index('hotel_id');
        });

        Schema::create('hotel_room_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('hotel_id');
            $table->string('name', 80);
            $table->string('beds', 80)->nullable();
            $table->unsignedTinyInteger('max_guests')->default(2);
            // FCFA per night, shown as "from". The reception confirms the exact price in the chat.
            $table->unsignedInteger('price_from')->default(0);
            $table->json('amenities')->nullable();
            $table->json('photos')->nullable();
            $table->string('description', 600)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['hotel_id', 'active']);
        });

        Schema::create('hotel_rooms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('hotel_id');
            $table->uuid('room_type_id');
            $table->string('number', 12);
            $table->string('floor', 40)->nullable();
            // ready | to_clean | out_of_order
            $table->string('state', 14)->default('ready');
            $table->string('state_note', 120)->nullable();
            $table->timestamp('state_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['hotel_id', 'number']);
            $table->index('room_type_id');
        });

        // Who has a room, from which night to which: kept for an E-Ride guest, booked by phone,
        // WhatsApp or at the desk, a guest in the house, or nights blocked for repairs.
        Schema::create('hotel_stays', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('hotel_id');
            $table->uuid('room_type_id');
            $table->uuid('room_id');
            $table->uuid('inquiry_id')->nullable();
            // eride | phone | whatsapp | walk_in | other | blocked
            $table->string('source', 10);
            // kept | booked | in_house | left | released | no_show
            $table->string('status', 10);
            $table->date('check_in');
            $table->date('check_out');
            $table->unsignedTinyInteger('guests')->default(1);
            $table->string('guest_name', 80)->nullable();
            $table->timestamp('kept_until')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['hotel_id', 'status']);
            $table->index('inquiry_id');
            $table->index(['status', 'kept_until']);
        });

        // One row per room per night taken. The unique key is what makes a double booking impossible.
        Schema::create('hotel_room_nights', function (Blueprint $table) {
            $table->id();
            $table->uuid('stay_id');
            $table->uuid('room_id');
            $table->date('night');
            $table->unique(['room_id', 'night']);
            $table->index('stay_id');
        });

        Schema::create('hotel_inquiries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('hotel_id');
            $table->uuid('customer_id');
            $table->uuid('room_type_id')->nullable();
            $table->date('check_in');
            $table->date('check_out');
            $table->unsignedTinyInteger('guests')->default(1);
            $table->unsignedTinyInteger('rooms')->default(1);
            // afternoon | evening | late | a HH:MM time
            $table->string('arrival', 10)->nullable();
            // open | answered | kept | arrived | closed
            $table->string('status', 10)->default('open');
            // guest | full | didnt_come | expired | stayed | hotel
            $table->string('close_reason', 12)->nullable();
            $table->unsignedInteger('price')->nullable();
            $table->timestamp('first_answer_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->unsignedSmallInteger('unread_hotel')->default(0);
            $table->unsignedSmallInteger('unread_guest')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['hotel_id', 'status', 'last_message_at']);
            $table->index(['customer_id', 'status']);
        });

        Schema::create('hotel_messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('inquiry_id');
            // guest | hotel | system
            $table->string('sender', 6);
            $table->uuid('user_id')->nullable();
            // text | request | price | kept | released | arrived | closed
            $table->string('kind', 10)->default('text');
            $table->string('body', 1000)->nullable();
            $table->json('data')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['inquiry_id', 'id']);
        });
    }

    public function down(): void
    {
        foreach (['hotel_messages', 'hotel_inquiries', 'hotel_room_nights', 'hotel_stays', 'hotel_rooms', 'hotel_room_types', 'hotel_staff', 'hotels'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
