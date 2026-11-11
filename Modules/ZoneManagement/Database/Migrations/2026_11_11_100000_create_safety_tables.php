<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Area pause, no-go areas and danger reports (roadmap feature 2 and 6).
 * A danger report warns E-Ride and nearby drivers. Two drivers reporting the
 * same spot make an automatic no-go area; an admin can also pause an area or a
 * whole zone, now or planned (a ghost-town day). See SafetyService.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('danger_reports')) {
            Schema::create('danger_reports', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('user_id')->index();
                $table->string('role', 10);                     // driver | customer
                $table->string('type', 20);                     // DangerReport::TYPES
                $table->string('place', 120)->nullable();       // the app's address text, if it had one
                $table->text('note')->nullable();
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->foreignUuid('zone_id')->nullable()->index();
                $table->foreignUuid('trip_request_id')->nullable();
                // open | confirmed | false | expired
                $table->string('status', 10)->default('open')->index();
                $table->foreignUuid('safety_area_id')->nullable()->index();
                $table->foreignUuid('handled_by')->nullable();
                $table->timestamp('handled_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('safety_areas')) {
            Schema::create('safety_areas', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('kind', 10);                     // pause | no_go
                $table->string('source', 10)->default('admin'); // admin | auto
                // A whole zone, or a circle.
                $table->foreignUuid('zone_id')->nullable()->index();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->unsignedInteger('radius_m')->nullable();
                $table->string('title', 120);                   // where, in words: "Mile 2 Nkwen"
                $table->string('reason', 191);                  // shown to riders and drivers
                $table->dateTime('starts_at')->index();
                $table->dateTime('ends_at')->index();
                $table->boolean('confirmed')->default(1);       // automatic ones wait for an admin
                $table->timestamp('lifted_at')->nullable();
                $table->foreignUuid('created_by')->nullable();
                $table->timestamp('pre_notified_at')->nullable();
                $table->timestamp('notified_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('safety_areas');
        Schema::dropIfExists('danger_reports');
    }
};
