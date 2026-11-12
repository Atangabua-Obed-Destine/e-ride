<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scheduled rides with the E-Ride features: what the scheduled-rides clock already
 * sent for a trip (safety heads-ups per area, "still looking for a driver"), so each
 * goes out once (ScheduledRideService).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('trip_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('trip_requests', 'schedule_marks')) {
                $table->json('schedule_marks')->nullable()->after('sending_notification_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('trip_requests', function (Blueprint $table) {
            if (Schema::hasColumn('trip_requests', 'schedule_marks')) {
                $table->dropColumn('schedule_marks');
            }
        });
    }
};
