<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Homes & spaces: the extras an owner ticks (internet, CCTV, parking...). Keys: Listing::AMENITIES. */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('listings') && !Schema::hasColumn('listings', 'amenities')) {
            Schema::table('listings', function (Blueprint $table) {
                $table->json('amenities')->nullable()->after('security');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('listings', 'amenities')) {
            Schema::table('listings', function (Blueprint $table) {
                $table->dropColumn('amenities');
            });
        }
    }
};
