<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * E-Ride as a city and tour guide.
 *
 * - Places get a guide: a story, entry fee, time to spend, best time and tips, in English and French.
 *   The AI drafts it, the team checks it and publishes it (guide_status).
 * - E-Ride riders rate places (stars and a few words), verified places and Google ones alike.
 * - Tours: ready-made routes in one city (a zone), each stop a verified place, each leg a normal ride.
 * - "Things to do" groups new Explore categories: places to eat, swim and party, sights and markets.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('places', function (Blueprint $table) {
            if (!Schema::hasColumn('places', 'guide_status')) {
                // none: no guide; draft: written, not checked; published: checked and shown in the app.
                $table->string('guide_status', 12)->default('none')->after('sort');
                $table->text('story')->nullable()->after('guide_status');
                $table->text('story_fr')->nullable()->after('story');
                // Entry fee in the local currency: null unknown, 0 free.
                $table->unsignedInteger('entry_fee')->nullable()->after('story_fr');
                $table->unsignedSmallInteger('stay_minutes')->nullable()->after('entry_fee');
                $table->string('best_time', 200)->nullable()->after('stay_minutes');
                $table->string('best_time_fr', 200)->nullable()->after('best_time');
                $table->text('tips')->nullable()->after('best_time_fr');
                $table->text('tips_fr')->nullable()->after('tips');
                $table->uuid('guide_checked_by')->nullable()->after('tips_fr');
                $table->timestamp('guide_checked_at')->nullable()->after('guide_checked_by');
            }
        });

        if (!Schema::hasTable('place_ratings')) {
            Schema::create('place_ratings', function (Blueprint $table) {
                $table->uuid('id')->primary();
                // The id the app knows the place by: a verified place's uuid, or "g:" + its Google id.
                $table->string('place_key', 200);
                $table->string('place_name', 150)->nullable();
                $table->uuid('user_id');
                $table->unsignedTinyInteger('stars');
                $table->string('comment', 300)->nullable();
                $table->boolean('is_visible')->default(true);
                $table->string('hidden_reason', 150)->nullable();
                $table->timestamps();
                $table->unique(['place_key', 'user_id']);
                $table->index(['place_key', 'is_visible']);
            });
        }

        if (!Schema::hasTable('tours')) {
            Schema::create('tours', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('zone_id')->nullable()->index();
                $table->string('slug', 140)->unique();
                $table->string('title', 120);
                $table->string('title_fr', 120)->nullable();
                $table->text('intro')->nullable();
                $table->text('intro_fr')->nullable();
                // draft: the team is still working on it; published: in the app; hidden: taken down.
                $table->string('status', 12)->default('draft');
                $table->boolean('is_featured')->default(false);
                $table->integer('sort')->default(0);
                $table->uuid('created_by')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('tour_stops')) {
            Schema::create('tour_stops', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('tour_id')->index();
                $table->uuid('place_id')->index();
                $table->unsignedSmallInteger('position');
                // What to do at this stop on this tour, when it differs from the place's own guide.
                $table->string('note', 400)->nullable();
                $table->string('note_fr', 400)->nullable();
                $table->unsignedSmallInteger('stay_minutes')->nullable();
                $table->timestamps();
                $table->unique(['tour_id', 'place_id']);
            });
        }

        if (Schema::hasTable('place_categories')) {
            $seed = [
                // key, name, name_fr, icon, google types, sort
                ['eat', 'Places to eat', 'Où manger', 'restaurant', ['restaurant', 'cafe', 'fast_food_restaurant', 'bakery'], 6],
                ['swim', 'Places to swim', 'Où nager', 'swim', ['swimming_pool', 'water_park'], 7],
                ['party', 'Bars & night out', 'Bars et sorties', 'party', ['night_club', 'bar', 'karaoke'], 8],
                ['market', 'Markets & shopping', 'Marchés et shopping', 'market', ['market', 'shopping_mall'], 9],
            ];
            foreach ($seed as [$key, $name, $nameFr, $icon, $types, $sort]) {
                if (DB::table('place_categories')->where('key', $key)->exists()) {
                    continue;
                }
                DB::table('place_categories')->insert([
                    'id' => (string)Str::uuid(), 'key' => $key, 'name' => $name, 'name_fr' => $nameFr, 'icon' => $icon,
                    'google_types' => json_encode($types), 'has_price' => 0, 'is_active' => 1, 'sort' => $sort,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_stops');
        Schema::dropIfExists('tours');
        Schema::dropIfExists('place_ratings');
        if (Schema::hasColumn('places', 'guide_status')) {
            Schema::table('places', function (Blueprint $table) {
                $table->dropColumn(['guide_status', 'story', 'story_fr', 'entry_fee', 'stay_minutes', 'best_time', 'best_time_fr',
                    'tips', 'tips_fr', 'guide_checked_by', 'guide_checked_at']);
            });
        }
        if (Schema::hasTable('place_categories')) {
            DB::table('place_categories')->whereIn('key', ['eat', 'swim', 'party', 'market'])->delete();
        }
    }
};
