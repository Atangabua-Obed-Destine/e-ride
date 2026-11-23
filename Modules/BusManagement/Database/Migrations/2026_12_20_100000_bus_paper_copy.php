<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The paper copy: agencies keep a pencil seat sheet per departure so they can sell on paper when the light
 * or the internet goes. bus_paper_seats is what the agents confirmed is pencilled on the sheet; the portal
 * compares it with the seats taken in E-Ride and asks them to pencil or erase the difference.
 * Agencies can pause sales in the app (stepping out, a cut), by hand or after unanswered requests.
 * Paper sales typed in after the bus left are flagged paper_sale.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('bus_paper_seats')) {
            Schema::create('bus_paper_seats', function (Blueprint $table) {
                $table->id();
                $table->uuid('departure_id');
                $table->string('seat', 8);
                $table->uuid('by_user_id')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->unique(['departure_id', 'seat']);
            });
        }
        Schema::table('bus_departures', function (Blueprint $table) {
            if (!Schema::hasColumn('bus_departures', 'paper_checked_at')) {
                $table->timestamp('paper_checked_at')->nullable();
                $table->uuid('paper_checked_by')->nullable();
            }
        });
        Schema::table('bus_agencies', function (Blueprint $table) {
            if (!Schema::hasColumn('bus_agencies', 'online_paused_at')) {
                $table->timestamp('online_paused_at')->nullable();
                $table->uuid('online_paused_by')->nullable();
                $table->boolean('online_pause_auto')->default(false);
                $table->unsignedSmallInteger('unanswered_streak')->default(0);
            }
        });
        Schema::table('bus_bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('bus_bookings', 'paper_sale')) {
                $table->boolean('paper_sale')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bus_paper_seats');
        if (Schema::hasColumn('bus_departures', 'paper_checked_at')) {
            Schema::table('bus_departures', fn(Blueprint $t) => $t->dropColumn(['paper_checked_at', 'paper_checked_by']));
        }
        if (Schema::hasColumn('bus_agencies', 'online_paused_at')) {
            Schema::table('bus_agencies', fn(Blueprint $t) => $t->dropColumn(['online_paused_at', 'online_paused_by', 'online_pause_auto', 'unanswered_streak']));
        }
        if (Schema::hasColumn('bus_bookings', 'paper_sale')) {
            Schema::table('bus_bookings', fn(Blueprint $t) => $t->dropColumn('paper_sale'));
        }
    }
};
