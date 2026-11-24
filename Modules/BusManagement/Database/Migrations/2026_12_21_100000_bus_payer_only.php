<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A counter sale that took only the details of the person paying: every ticket carries their name. */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('bus_bookings', 'payer_only')) {
            Schema::table('bus_bookings', function (Blueprint $t) {
                $t->boolean('payer_only')->default(false)->after('paper_sale');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('bus_bookings', 'payer_only')) {
            Schema::table('bus_bookings', fn(Blueprint $t) => $t->dropColumn('payer_only'));
        }
    }
};
