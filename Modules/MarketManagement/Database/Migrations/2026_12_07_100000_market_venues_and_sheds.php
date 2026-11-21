<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Markets (Bamenda Main Market, Nkwen Market, Food Market...) that E-Ride
 * creates, and the shops inside them: shed number, block or line, a photo of
 * the shopfront and what the shop sells, so a buyer can find the shed on foot.
 * A verified shop in a market means E-Ride confirmed the shed, its place and
 * what it sells.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('market_venues')) {
            Schema::create('market_venues', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->string('name', 80);
                $t->string('slug', 90)->unique();
                $t->string('city', 60)->default('Bamenda');
                $t->string('area_label', 120)->nullable();
                $t->string('about', 500)->nullable();
                $t->decimal('latitude', 10, 7);
                $t->decimal('longitude', 10, 7);
                // The gates: [{"name": "Main gate", "lat": .., "lng": ..}]
                $t->json('entrances')->nullable();
                // Blocks or lines the market is known by, suggested to shop owners: ["Block A", "Line 4"]
                $t->json('blocks')->nullable();
                $t->boolean('is_active')->default(true)->index();
                $t->unsignedSmallInteger('sort')->default(0);
                $t->timestamps();
            });
        }
        Schema::table('market_shops', function (Blueprint $t) {
            if (!Schema::hasColumn('market_shops', 'venue_id')) {
                $t->uuid('venue_id')->nullable()->after('kind')->index();
                $t->string('shed_number', 30)->nullable()->after('venue_id');
                $t->string('shed_block', 40)->nullable()->after('shed_number');
                $t->string('shopfront')->nullable()->after('shed_block');
                // What the shop sells, in buyers' words: ["school shoes", "bags"], and "|school shoes|bags|" to search.
                $t->json('sells')->nullable()->after('shopfront');
                $t->string('sells_search', 700)->nullable()->after('sells');
                // When the place last changed (a verified shop that moves is checked again).
                $t->timestamp('shed_changed_at')->nullable()->after('sells_search');
            }
        });
    }

    public function down(): void
    {
        Schema::table('market_shops', function (Blueprint $t) {
            if (Schema::hasColumn('market_shops', 'venue_id')) {
                $t->dropColumn(['venue_id', 'shed_number', 'shed_block', 'shopfront', 'sells', 'sells_search', 'shed_changed_at']);
            }
        });
        Schema::dropIfExists('market_venues');
    }
};
