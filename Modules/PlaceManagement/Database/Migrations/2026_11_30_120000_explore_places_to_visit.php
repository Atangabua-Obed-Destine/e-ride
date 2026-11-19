<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Explore gets "Places to visit": sights, museums, parks and nature around the city, the start of
 * E-Ride as a city and tour guide. Google lists them; E-Ride can verify the best ones with photos
 * and a description, like any other Explore place.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('place_categories') || DB::table('place_categories')->where('key', 'visit')->exists()) {
            return;
        }
        DB::table('place_categories')->insert([
            'id' => (string)Str::uuid(),
            'key' => 'visit',
            'name' => 'Places to visit',
            'name_fr' => 'Lieux à visiter',
            // The app shows it with a landmark icon; older apps fall back to a pin.
            'icon' => 'landmark',
            'google_types' => json_encode(['tourist_attraction', 'museum', 'park', 'national_park', 'hiking_area', 'art_gallery']),
            'has_price' => 0,
            'is_active' => 1,
            'sort' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('place_categories')) {
            DB::table('place_categories')->where('key', 'visit')->delete();
        }
    }
};
