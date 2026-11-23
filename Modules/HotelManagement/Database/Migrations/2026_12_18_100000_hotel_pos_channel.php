<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * POS Innova hotels (docs/posinnova-hotel-contract.md): a hotel linked to the hotel module of its POS
 * Innova by a key, the POS room types with their free rooms per night, and on stays the POS reservation
 * they mirror. POS always calls E-Ride; E-Ride never writes into POS.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('hotel_pos_links')) {
            Schema::create('hotel_pos_links', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('hotel_id')->index();
                $table->string('key_hash', 64)->unique();          // sha256 of erh_...
                $table->string('key_hint', 12);
                $table->string('pos_version', 40)->nullable();
                $table->date('hotel_day')->nullable();             // POS's hotel day at the last availability push
                $table->timestamp('linked_at')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamp('last_heartbeat_at')->nullable();
                $table->timestamp('last_availability_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->uuid('created_by')->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('hotel_pos_types')) {
            Schema::create('hotel_pos_types', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('link_id')->index();
                $table->unsignedBigInteger('pos_type_id');
                $table->string('name', 120);
                $table->unsignedSmallInteger('capacity')->default(1);
                $table->unsignedInteger('eride_price')->default(0);
                $table->unsignedSmallInteger('rooms_total')->default(0);
                $table->date('free_from')->nullable();             // free[0] is this night
                $table->json('free')->nullable();                  // rooms free per night from free_from
                $table->uuid('room_type_id')->nullable()->index(); // the E-Ride room type it is mapped to
                $table->uuid('snapshot_id')->nullable();
                $table->timestamp('removed_at')->nullable();
                $table->timestamps();
                $table->unique(['link_id', 'pos_type_id']);
            });
        }
        Schema::table('hotel_stays', function (Blueprint $table) {
            $table->uuid('room_id')->nullable()->change();         // POS gives the room for POS-held stays
            if (!Schema::hasColumn('hotel_stays', 'pos_ref')) {
                $table->string('pos_ref', 60)->nullable();
                $table->timestamp('pos_release_due_at')->nullable();  // ended on E-Ride: POS must cancel it
                $table->string('pos_release_reason', 20)->nullable();
                $table->timestamp('pos_release_seen_at')->nullable();
                $table->index(['inquiry_id', 'pos_ref']);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hotel_pos_types');
        Schema::dropIfExists('hotel_pos_links');
    }
};
