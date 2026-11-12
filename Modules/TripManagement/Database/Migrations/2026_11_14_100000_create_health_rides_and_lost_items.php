<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Health rides and lost and found (roadmap feature D).
 * A health ride is an urgent ride to care: no bidding, no surge, sent to drivers
 * over a wider radius, shown in red to the driver (HealthRideService).
 * A lost item is a rider's report of something left in the car after a ride,
 * followed until it is back (LostItemService).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('trip_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('trip_requests', 'is_health_ride')) {
                $table->boolean('is_health_ride')->default(0)->index()->after('drop_fare_price');
            }
        });

        if (!Schema::hasTable('lost_items')) {
            Schema::create('lost_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('trip_request_id')->unique();     // one case per trip
                $table->foreignUuid('customer_id')->index();
                $table->foreignUuid('driver_id')->index();
                $table->text('description');                           // what it is, in the rider's words
                $table->string('photo')->nullable();                   // file name in storage/app/public/lost-item
                // reported -> found | not_found -> returned; or cancelled (rider) / closed (E-Ride).
                $table->string('status', 20)->default('reported')->index();
                $table->string('return_method', 10)->nullable();       // collect (rider fetches it) | deliver (driver brings it)
                $table->decimal('return_fee', 24, 2)->default(0);      // deliver only; paid to the driver in cash
                $table->string('driver_note', 500)->nullable();        // e.g. "Under the back seat, I'm at Mile 4"
                $table->text('admin_note')->nullable();
                $table->timestamp('answered_at')->nullable();          // the driver said found / not found
                $table->timestamp('reminded_at')->nullable();          // the driver was reminded to answer
                $table->timestamp('driver_returned_at')->nullable();   // the driver says it is back with the rider
                $table->timestamp('returned_at')->nullable();          // confirmed back
                $table->timestamp('escalated_at')->nullable();         // handed to E-Ride support
                $table->timestamp('closed_at')->nullable();
                $table->string('closed_by', 10)->nullable();           // customer | driver | admin | system
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lost_items');
        Schema::table('trip_requests', function (Blueprint $table) {
            if (Schema::hasColumn('trip_requests', 'is_health_ride')) {
                $table->dropIndex(['is_health_ride']);
                $table->dropColumn('is_health_ride');
            }
        });
    }
};
