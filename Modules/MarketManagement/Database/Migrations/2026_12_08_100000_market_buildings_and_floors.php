<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A market can have buildings with floors: a shed is then in a building, on
 * a floor (0 = ground floor, -1 = basement), in a block, with its number. The
 * phone cannot tell floors apart, so the floor is said in words.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('market_venues', function (Blueprint $t) {
            if (!Schema::hasColumn('market_venues', 'buildings')) {
                // [{"name": "Main hall", "floors": 3}]: floors counts the ground floor.
                $t->json('buildings')->nullable()->after('blocks');
            }
        });
        Schema::table('market_shops', function (Blueprint $t) {
            if (!Schema::hasColumn('market_shops', 'shed_building')) {
                $t->string('shed_building', 60)->nullable()->after('venue_id');
                $t->tinyInteger('shed_floor')->nullable()->after('shed_building');
            }
        });
    }

    public function down(): void
    {
        Schema::table('market_shops', function (Blueprint $t) {
            if (Schema::hasColumn('market_shops', 'shed_building')) {
                $t->dropColumn(['shed_building', 'shed_floor']);
            }
        });
        Schema::table('market_venues', function (Blueprint $t) {
            if (Schema::hasColumn('market_venues', 'buildings')) {
                $t->dropColumn('buildings');
            }
        });
    }
};
