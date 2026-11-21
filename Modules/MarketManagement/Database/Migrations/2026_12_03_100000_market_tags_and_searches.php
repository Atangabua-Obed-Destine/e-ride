<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Market tags and what people search for.
 *
 * - Items get tags: the other words buyers use for the same thing ("ps4":
 *   playstation, console, jeux vidéo). tags_search is the same list as
 *   "|ps4|playstation|" for a cheap LIKE match.
 * - market_search_terms counts searches per word and day, and those that found
 *   nothing (misses), so sellers see "12 people looked for ps4 this week".
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('market_items', function (Blueprint $t) {
            if (!Schema::hasColumn('market_items', 'tags')) {
                $t->json('tags')->nullable()->after('category');
                $t->string('tags_search', 500)->nullable()->after('tags');
            }
        });
        if (!Schema::hasTable('market_search_terms')) {
            Schema::create('market_search_terms', function (Blueprint $t) {
                $t->id();
                $t->string('term', 60);
                $t->date('day');
                $t->unsignedInteger('searches')->default(0);
                $t->unsignedInteger('misses')->default(0);
                $t->timestamps();
                $t->unique(['term', 'day']);
                $t->index('day');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('market_search_terms');
        if (Schema::hasColumn('market_items', 'tags')) {
            Schema::table('market_items', fn(Blueprint $t) => $t->dropColumn(['tags', 'tags_search']));
        }
    }
};
