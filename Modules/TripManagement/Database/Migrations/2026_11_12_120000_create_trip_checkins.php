<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trip check-ins and night sharing (roadmap features 7 and 8). The server
 * watches rides in progress; when one stops too long, leaves its route or runs
 * far too long, the rider is asked "Are you OK?". No answer, or "I need help",
 * opens a safety alert and tells the rider's trusted contacts. A trusted
 * contact can be chosen to follow every night ride. See TripCheckinService.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('trip_checkins')) {
            Schema::create('trip_checkins', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('trip_request_id')->index();
                $table->foreignUuid('customer_id')->index();
                $table->foreignUuid('driver_id')->nullable();
                $table->string('reason', 12);                    // stopped | off_route | too_long
                // asked | ok | help | no_answer | closed (the ride ended first)
                $table->string('status', 10)->default('asked')->index();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->timestamp('asked_at')->nullable();
                $table->timestamp('answered_at')->nullable();
                $table->timestamp('escalated_at')->nullable();
                $table->foreignUuid('safety_alert_id')->nullable();        // safety_alerts ids are uuids
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('trip_watches')) {
            // What the watcher remembers between minutes, per ride.
            Schema::create('trip_watches', function (Blueprint $table) {
                $table->foreignUuid('trip_request_id')->primary();
                $table->decimal('anchor_latitude', 10, 7)->nullable();   // where the car last moved from
                $table->decimal('anchor_longitude', 10, 7)->nullable();
                $table->timestamp('anchor_at')->nullable();
                $table->unsignedTinyInteger('off_route_hits')->default(0);
                $table->timestamp('quiet_until')->nullable();            // after "I'm OK"
                $table->boolean('too_long_asked')->default(0);
                $table->timestamp('night_shared_at')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('trusted_contacts') && !Schema::hasColumn('trusted_contacts', 'night_share')) {
            Schema::table('trusted_contacts', function (Blueprint $table) {
                $table->boolean('night_share')->default(0)->after('phone');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_watches');
        Schema::dropIfExists('trip_checkins');
        if (Schema::hasColumn('trusted_contacts', 'night_share')) {
            Schema::table('trusted_contacts', fn(Blueprint $table) => $table->dropColumn('night_share'));
        }
    }
};
