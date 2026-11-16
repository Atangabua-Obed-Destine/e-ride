<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lockdowns and ghost towns: an agency declares a "no travel" period (once, a range, or a weekday that repeats),
 * for all or some destinations, and chooses what happens to passengers (moved to the next departure, refunded,
 * or the departures put "at risk" until it decides).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('bus_closures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('agency_id')->index();
            // lockdown | ghost_town | road_closed | strike | other
            $table->string('kind', 12);
            $table->string('note', 300)->nullable();
            // A period: from .. to. A repeating day: weekday bits (mon 1 .. sun 64), from .. until (dates), and the hours.
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedTinyInteger('days')->nullable();
            $table->time('day_from')->nullable();
            $table->time('day_to')->nullable();
            // All destinations when null.
            $table->json('destination_ids')->nullable();
            // move | cancel | at_risk (until decided)
            $table->string('outcome', 8);
            $table->timestamp('decide_by')->nullable();
            // active | lifted
            $table->string('status', 8)->default('active');
            $table->uuid('safety_area_id')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('lifted_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        Schema::table('bus_departures', function (Blueprint $table) {
            // A closure that stops this departure (cancelled), or puts it at risk (not sold until decided).
            $table->uuid('closure_id')->nullable()->after('cancel_reason')->index();
        });
        Schema::table('bus_bookings', function (Blueprint $table) {
            // Moved off a closed departure: free cancellation until the trip, and where it came from.
            $table->uuid('closure_id')->nullable()->after('collect_reminded_at');
            $table->uuid('moved_from_departure_id')->nullable()->after('closure_id');
            $table->timestamp('moved_at')->nullable()->after('moved_from_departure_id');
            $table->timestamp('move_kept_at')->nullable()->after('moved_at');
        });
    }

    public function down(): void
    {
        Schema::table('bus_bookings', function (Blueprint $table) {
            $table->dropColumn(['closure_id', 'moved_from_departure_id', 'moved_at', 'move_kept_at']);
        });
        Schema::table('bus_departures', function (Blueprint $table) {
            $table->dropColumn('closure_id');
        });
        Schema::dropIfExists('bus_closures');
    }
};
