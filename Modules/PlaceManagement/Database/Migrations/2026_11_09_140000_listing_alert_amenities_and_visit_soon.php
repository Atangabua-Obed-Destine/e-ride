<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Homes & spaces: saved searches can require extras (wifi, cctv...), and a
 * second visit reminder goes out about 2 hours before the visit.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('listing_alerts') && !Schema::hasColumn('listing_alerts', 'amenities')) {
            Schema::table('listing_alerts', function (Blueprint $table) {
                $table->json('amenities')->nullable()->after('verified_only');
            });
        }
        if (Schema::hasTable('listing_visits') && !Schema::hasColumn('listing_visits', 'soon_reminded_at')) {
            Schema::table('listing_visits', function (Blueprint $table) {
                $table->timestamp('soon_reminded_at')->nullable()->after('reminded_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('listing_alerts', 'amenities')) {
            Schema::table('listing_alerts', fn(Blueprint $table) => $table->dropColumn('amenities'));
        }
        if (Schema::hasColumn('listing_visits', 'soon_reminded_at')) {
            Schema::table('listing_visits', fn(Blueprint $table) => $table->dropColumn('soon_reminded_at'));
        }
    }
};
