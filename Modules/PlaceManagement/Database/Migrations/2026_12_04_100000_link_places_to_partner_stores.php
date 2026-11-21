<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A place that is also an E-Ride partner store (Food & Shops): the app offers
 * "Order" on it. Places are matched to stores by name and location on their
 * own; store_id is the team's explicit link when the names differ.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('places', function (Blueprint $t) {
            if (!Schema::hasColumn('places', 'store_id')) {
                $t->uuid('store_id')->nullable()->after('google_place_id');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('places', 'store_id')) {
            Schema::table('places', fn(Blueprint $t) => $t->dropColumn('store_id'));
        }
    }
};
