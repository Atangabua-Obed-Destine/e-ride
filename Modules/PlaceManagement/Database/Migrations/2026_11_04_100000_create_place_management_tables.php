<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Places to stay. E-Ride's own verified listings live in `places`; nearby
 * lodging from Google Places is fetched live and never stored, apart from the
 * Google place IDs an admin hid or linked to a listing (Google allows keeping
 * place IDs). `place_google_usage` counts Google calls per month so a cap can
 * stop the bill from running away.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('places')) {
            Schema::create('places', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name', 120);
                $table->string('category', 30)->default('hotel')->index();
                $table->string('google_place_id', 255)->nullable()->index();
                $table->text('description')->nullable();
                $table->string('address');
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->foreignUuid('zone_id')->nullable()->index();
                $table->string('phone', 25)->nullable();
                $table->string('whatsapp', 25)->nullable();
                $table->string('website')->nullable();
                $table->decimal('price_min', 24, 2)->nullable();
                $table->decimal('price_max', 24, 2)->nullable();
                $table->json('amenities')->nullable();
                $table->json('photos')->nullable();
                $table->string('check_in', 5)->nullable();
                $table->string('check_out', 5)->nullable();
                $table->boolean('reception_24h')->default(0);
                $table->boolean('is_featured')->default(0);
                $table->boolean('is_active')->default(1);
                $table->integer('sort')->default(0);
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('place_google_hidden')) {
            Schema::create('place_google_hidden', function (Blueprint $table) {
                $table->id();
                $table->string('google_place_id', 255)->unique();
                // What the admin called it when hiding, so the list is readable.
                $table->string('label', 150)->nullable();
                $table->string('reason', 255)->nullable();
                $table->foreignUuid('created_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('place_google_usage')) {
            Schema::create('place_google_usage', function (Blueprint $table) {
                $table->char('month', 7);
                $table->string('kind', 20);
                $table->unsignedInteger('count')->default(0);
                $table->primary(['month', 'kind']);
            });
        }

        insertBusinessSetting(keyName: 'stay_status', settingType: PLACE_SETTINGS, value: '0');
        insertBusinessSetting(keyName: 'stay_label', settingType: PLACE_SETTINGS, value: 'Hotels');
        insertBusinessSetting(keyName: 'stay_google_status', settingType: PLACE_SETTINGS, value: '1');
        insertBusinessSetting(keyName: 'stay_radius_km', settingType: PLACE_SETTINGS, value: '10');
        insertBusinessSetting(keyName: 'stay_min_reviews', settingType: PLACE_SETTINGS, value: '5');
        insertBusinessSetting(keyName: 'stay_google_search_cap', settingType: PLACE_SETTINGS, value: '1000');
        insertBusinessSetting(keyName: 'stay_google_photo_cap', settingType: PLACE_SETTINGS, value: '1000');
    }

    public function down(): void
    {
        Schema::dropIfExists('place_google_usage');
        Schema::dropIfExists('place_google_hidden');
        Schema::dropIfExists('places');
    }
};
