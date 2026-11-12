<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Landmarks and drop fares (roadmap feature 9 and 10).
 * Landmarks are the places Bamenda people give directions by ("Hospital
 * Roundabout", "Mile 4 Total"); they come first in the app's place search.
 * A drop fare is a fixed price between two landmarks for one vehicle category,
 * like a taxi "drop": no per-km, waiting, delay or surge charges.
 * See LandmarkService and DropFareService.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('landmarks')) {
            Schema::create('landmarks', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name', 120);
                $table->text('aliases')->nullable();               // other names people use, one per line
                $table->string('area', 120)->nullable();           // "Nkwen", "Commercial Avenue"
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->foreignUuid('zone_id')->nullable()->index();
                $table->foreignUuid('place_id')->nullable()->index(); // imported from Explore Bamenda
                $table->boolean('is_active')->default(1)->index();
                $table->unsignedInteger('uses')->default(0);         // chosen in a search
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('drop_fares')) {
            Schema::create('drop_fares', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('from_landmark_id')->index();
                $table->foreignUuid('to_landmark_id')->index();
                $table->foreignUuid('vehicle_category_id')->index();
                $table->decimal('price', 24, 2);                     // what the rider pays, VAT included
                $table->boolean('both_ways')->default(1);
                $table->unsignedSmallInteger('radius_m')->default(400); // catchment around each landmark
                $table->unsignedTinyInteger('hour_from')->nullable();   // e.g. 6 (06:00)
                $table->unsignedTinyInteger('hour_to')->nullable();     // e.g. 20 (20:00)
                $table->boolean('is_active')->default(1);
                $table->unsignedInteger('uses')->default(0);
                $table->timestamps();
            });
        }

        Schema::table('trip_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('trip_requests', 'drop_fare_id')) {
                $table->foreignUuid('drop_fare_id')->nullable()->after('is_move');
            }
            if (!Schema::hasColumn('trip_requests', 'drop_fare_price')) {
                $table->decimal('drop_fare_price', 24, 2)->nullable()->after('drop_fare_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('trip_requests', function (Blueprint $table) {
            foreach (['drop_fare_price', 'drop_fare_id'] as $column) {
                if (Schema::hasColumn('trip_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
        Schema::dropIfExists('drop_fares');
        Schema::dropIfExists('landmarks');
    }
};
