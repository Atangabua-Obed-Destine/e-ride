<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Homes & spaces (Explore Bamenda): places to rent that customers list
 * themselves. E-Ride reviews each one before it is public, can verify it with
 * a staff visit, and keeps it honest with the "still available" policy (the
 * owner confirms on a schedule, or the listing is flagged, then paused).
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('listings')) {
            Schema::create('listings', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('owner_id')->index();
                $table->string('type', 20)->index();              // Listing::TYPES
                $table->string('title', 80);
                $table->text('description')->nullable();
                $table->string('neighbourhood', 60)->index();
                $table->string('address', 191)->nullable();
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->foreignUuid('zone_id')->nullable()->index();
                $table->unsignedSmallInteger('units_available')->default(1);
                $table->boolean('furnished')->default(0);
                $table->decimal('rent', 24, 2);
                $table->string('rent_period', 10)->default('month'); // month | year
                $table->unsignedTinyInteger('advance_months')->nullable();
                $table->decimal('caution', 24, 2)->nullable();
                $table->date('available_from')->nullable();
                $table->string('water', 20)->nullable();          // tap | borehole | well | none
                $table->string('power', 20)->nullable();          // prepaid_meter | shared_meter | none
                $table->boolean('backup_power')->default(0);
                $table->string('toilet', 10)->nullable();         // private | shared
                $table->string('kitchen', 10)->nullable();        // private | shared | none
                $table->boolean('fenced')->default(0);
                $table->boolean('security')->default(0);
                $table->string('owner_role', 12)->default('owner'); // owner | caretaker
                // pending | changes | live | paused | rented | refused | removed
                $table->string('status', 12)->default('pending')->index();
                $table->text('review_note')->nullable();
                $table->boolean('needs_review')->default(0);      // a live listing the owner edited
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('last_confirmed_at')->nullable();
                $table->unsignedTinyInteger('reminder_stage')->default(0);
                $table->timestamp('paused_at')->nullable();
                $table->timestamp('taken_check_until')->nullable(); // 2 "already taken" reports: confirm before this
                $table->timestamp('verified_until')->nullable();
                $table->timestamp('verified_expiry_notified_at')->nullable();
                $table->timestamp('stale_nudged_at')->nullable();
                $table->timestamp('rented_at')->nullable();
                $table->unsignedInteger('views')->default(0);
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('listing_photos')) {
            Schema::create('listing_photos', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('listing_id')->index();
                $table->string('file', 191);
                $table->string('source', 10)->default('owner');   // owner | eride
                $table->decimal('latitude', 10, 7)->nullable();   // E-Ride photos: where and when taken
                $table->decimal('longitude', 10, 7)->nullable();
                $table->timestamp('taken_at')->nullable();
                $table->unsignedSmallInteger('sort')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('listing_visits')) {
            Schema::create('listing_visits', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('listing_id')->index();
                $table->foreignUuid('seeker_id')->index();
                $table->foreignUuid('owner_id')->index();
                $table->date('visit_date');
                $table->string('time_window', 12);                // morning | afternoon | evening
                // requested | proposed | accepted | declined | cancelled | done
                $table->string('status', 12)->default('requested')->index();
                $table->date('proposed_date')->nullable();
                $table->string('proposed_window', 12)->nullable();
                $table->string('outcome', 20)->nullable();        // as_described | not_as_described | taken | no_show
                $table->text('outcome_note')->nullable();
                $table->boolean('taking')->default(0);
                $table->timestamp('reminded_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('listing_verifications')) {
            Schema::create('listing_verifications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('listing_id')->index();
                $table->foreignUuid('staff_id')->nullable()->index();
                $table->timestamp('scheduled_for')->nullable();
                $table->string('status', 12)->default('requested')->index(); // requested | scheduled | passed | fixable | failed
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->unsignedInteger('distance_m')->nullable();
                $table->json('checks')->nullable();
                $table->text('note')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('listing_reports')) {
            Schema::create('listing_reports', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('listing_id')->index();
                $table->foreignUuid('reporter_id')->index();
                $table->foreignUuid('visit_id')->nullable();
                $table->string('reason', 24);                     // ListingReport::REASONS
                $table->text('note')->nullable();
                $table->string('status', 12)->default('open')->index(); // open | upheld | blocked | dismissed
                $table->foreignUuid('handled_by')->nullable();
                $table->timestamp('handled_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('listing_alerts')) {
            Schema::create('listing_alerts', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('user_id')->index();
                $table->string('type', 20)->nullable();
                $table->string('neighbourhood', 60)->nullable();
                $table->decimal('max_monthly_rent', 24, 2)->nullable();
                $table->boolean('verified_only')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('listing_owners')) {
            // Strikes are upheld reports; this holds what they lead to.
            Schema::create('listing_owners', function (Blueprint $table) {
                $table->foreignUuid('owner_id')->primary();
                $table->timestamp('banned_until')->nullable();
                $table->boolean('blocked')->default(0);
                $table->timestamps();
            });
        }

        // The Explore Bamenda tile. No Google types: these places are only customers' listings.
        // Off until the app version with the Homes & spaces screens is out: switch it on in
        // Explore Bamenda > Categories (older apps would only open an empty list).
        if (Schema::hasTable('place_categories') && !DB::table('place_categories')->where('key', 'homes')->exists()) {
            DB::table('place_categories')->insert([
                'id' => (string)Str::uuid(), 'key' => 'homes', 'name' => 'Homes & spaces', 'name_fr' => 'Logements et espaces',
                'icon' => 'home', 'google_types' => json_encode([]), 'has_price' => 1, 'is_active' => 0, 'sort' => 5,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        insertBusinessSetting(keyName: 'listing_neighbourhoods', settingType: PLACE_SETTINGS,
            value: 'Bambili, Bambui, Mile 3 Nkwen, Mile 4 Nkwen, Up Station, Commercial Avenue, Ntarinkon, Mankon, Old Town, Foncha Street');
    }

    public function down(): void
    {
        foreach (['listing_owners', 'listing_alerts', 'listing_reports', 'listing_verifications', 'listing_visits', 'listing_photos', 'listings'] as $table) {
            Schema::dropIfExists($table);
        }
        DB::table('place_categories')->where('key', 'homes')->delete();
    }
};
