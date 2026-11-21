<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A store can turn delivery off (on by default). Eating there (restaurants)
 * and collecting are always offered, so their switches go.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $t) {
            if (!Schema::hasColumn('stores', 'offers_delivery')) {
                $t->boolean('offers_delivery')->default(true)->after('is_open');
            }
        });
        if (Schema::hasColumn('stores', 'offers_dine_in')) {
            Schema::table('stores', fn(Blueprint $t) => $t->dropColumn(['offers_dine_in', 'offers_pickup']));
        }
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $t) {
            if (!Schema::hasColumn('stores', 'offers_dine_in')) {
                $t->boolean('offers_dine_in')->default(true)->after('is_open');
                $t->boolean('offers_pickup')->default(true)->after('offers_dine_in');
            }
        });
        if (Schema::hasColumn('stores', 'offers_delivery')) {
            Schema::table('stores', fn(Blueprint $t) => $t->dropColumn('offers_delivery'));
        }
    }
};
