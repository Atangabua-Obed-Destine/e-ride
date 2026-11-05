<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Share trip: every trip has a random token for its public link (/t/{token}),
 * live from the driver's acceptance until the trip ends. Trusted contacts are
 * the people a customer shares with in one tap.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('trip_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('trip_requests', 'share_token')) {
                $table->string('share_token', 40)->nullable()->unique();
            }
            if (!Schema::hasColumn('trip_requests', 'shared_at')) {
                // First time the customer opened the share link.
                $table->timestamp('shared_at')->nullable();
            }
        });

        // Trips in progress get a token now; new trips get one when created.
        DB::table('trip_requests')
            ->whereNull('share_token')
            ->whereIn('current_status', ['pending', 'accepted', 'out_for_pickup', 'ongoing', 'returning'])
            ->pluck('id')
            ->each(fn($id) => DB::table('trip_requests')->where('id', $id)->update(['share_token' => Str::random(32)]));

        if (!Schema::hasTable('trusted_contacts')) {
            Schema::create('trusted_contacts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('user_id')->index();
                $table->string('name', 100);
                $table->string('phone', 30);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trusted_contacts');
        Schema::table('trip_requests', function (Blueprint $table) {
            if (Schema::hasColumn('trip_requests', 'share_token')) {
                $table->dropUnique(['share_token']);
                $table->dropColumn('share_token');
            }
            if (Schema::hasColumn('trip_requests', 'shared_at')) {
                $table->dropColumn('shared_at');
            }
        });
    }
};
