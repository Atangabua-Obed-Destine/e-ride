<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A partner's public store page ([domain]/f/<slug>), shared on WhatsApp:
 * visits (once per visitor and day) and the new riders it brought (counted
 * once per rider, from the install referrer), so partners see it works.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $t) {
            if (!Schema::hasColumn('stores', 'link_visits')) {
                $t->unsignedInteger('link_visits')->default(0)->after('offers_delivery');
                $t->unsignedInteger('link_installs')->default(0)->after('link_visits');
            }
        });
        if (!Schema::hasTable('store_link_visits')) {
            Schema::create('store_link_visits', function (Blueprint $t) {
                $t->date('day');
                $t->uuid('store_id');
                $t->char('visitor', 40);
                $t->unique(['day', 'store_id', 'visitor']);
            });
        }
        if (!Schema::hasTable('store_link_referrals')) {
            Schema::create('store_link_referrals', function (Blueprint $t) {
                $t->id();
                $t->uuid('user_id')->unique();
                $t->uuid('store_id')->index();
                $t->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('store_link_referrals');
        Schema::dropIfExists('store_link_visits');
        Schema::table('stores', function (Blueprint $t) {
            if (Schema::hasColumn('stores', 'link_visits')) {
                $t->dropColumn(['link_visits', 'link_installs']);
            }
        });
    }
};
