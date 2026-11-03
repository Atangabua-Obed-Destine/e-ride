<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Explore Bamenda: places to stay become one category among several
 * (pharmacies, hospitals and clinics, banks and ATMs, bus agencies). The
 * categories are data, so the admin can add one without a code change.
 * Pharmacies can be marked on duty (pharmacie de garde) for a date range;
 * an expired range simply stops showing.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('place_categories')) {
            Schema::create('place_categories', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('key', 40)->unique();
                $table->string('name', 80);
                $table->string('name_fr', 80)->nullable();
                $table->string('icon', 30)->default('place');
                // Google Places (New) "Table A" types searched for this category.
                $table->json('google_types')->nullable();
                $table->boolean('has_price')->default(0);
                $table->boolean('is_active')->default(1);
                $table->integer('sort')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasColumn('places', 'explore_category')) {
            Schema::table('places', function (Blueprint $table) {
                // Existing listings are all places to stay.
                $table->string('explore_category', 40)->default('stay')->index()->after('category');
                $table->date('on_duty_from')->nullable()->after('reception_24h');
                $table->date('on_duty_until')->nullable()->after('on_duty_from');
            });
        }

        $seed = [
            ['pharmacy', 'Pharmacies', 'Pharmacies', 'pharmacy', ['pharmacy', 'drugstore'], 0, 10],
            ['health', 'Hospitals & clinics', 'Hôpitaux et cliniques', 'hospital', ['hospital', 'doctor'], 0, 20],
            ['bank', 'Banks & ATMs', 'Banques et GAB', 'bank', ['bank', 'atm'], 0, 30],
            ['bus', 'Bus agencies', 'Agences de voyage', 'bus', ['bus_station', 'transit_station', 'travel_agency'], 0, 40],
            ['stay', 'Hotels & guest houses', 'Hôtels et auberges', 'bed', ['lodging'], 1, 50],
        ];
        foreach ($seed as [$key, $name, $nameFr, $icon, $types, $hasPrice, $sort]) {
            if (!DB::table('place_categories')->where('key', $key)->exists()) {
                DB::table('place_categories')->insert([
                    'id' => (string)Str::uuid(), 'key' => $key, 'name' => $name, 'name_fr' => $nameFr, 'icon' => $icon,
                    'google_types' => json_encode($types), 'has_price' => $hasPrice, 'is_active' => 1, 'sort' => $sort,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }

        insertBusinessSetting(keyName: 'explore_label', settingType: PLACE_SETTINGS, value: 'Explore Bamenda');
        // Shown on Hospitals & clinics, e.g. the emergency numbers. Empty = hidden.
        insertBusinessSetting(keyName: 'explore_emergency_note', settingType: PLACE_SETTINGS, value: '');
    }

    public function down(): void
    {
        if (Schema::hasColumn('places', 'explore_category')) {
            Schema::table('places', function (Blueprint $table) {
                $table->dropColumn(['explore_category', 'on_duty_from', 'on_duty_until']);
            });
        }
        Schema::dropIfExists('place_categories');
    }
};
