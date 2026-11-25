<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bus cost centres on the ledger (paxhitestbed's advice: tag lines from day one).
 * A line posted from the bus records carries the trip, the bus, the destination,
 * the channel (app | counter) and the counter agent, so profit per trip, bus or
 * route and each agent's cash read straight from posted lines. A fixed asset can
 * be a bus of the fleet.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::table('journal_entry_lines', function (Blueprint $t) {
            $t->string('departure_id', 36)->nullable()->after('contact_id');
            $t->string('vehicle_id', 36)->nullable()->after('departure_id');
            $t->string('destination_id', 36)->nullable()->after('vehicle_id');
            $t->string('channel', 10)->nullable()->after('destination_id');
            $t->string('agent_id', 36)->nullable()->after('channel');
            $t->index(['business_id', 'departure_id']);
            $t->index(['business_id', 'vehicle_id']);
            $t->index(['business_id', 'agent_id']);
        });
        Schema::table('fixed_assets', function (Blueprint $t) {
            $t->string('vehicle_id', 36)->nullable()->index();
        });
    }

    public function down()
    {
        Schema::table('fixed_assets', fn (Blueprint $t) => $t->dropColumn('vehicle_id'));
        Schema::table('journal_entry_lines', function (Blueprint $t) {
            $t->dropIndex(['business_id', 'departure_id']);
            $t->dropIndex(['business_id', 'vehicle_id']);
            $t->dropIndex(['business_id', 'agent_id']);
            $t->dropColumn(['departure_id', 'vehicle_id', 'destination_id', 'channel', 'agent_id']);
        });
    }
};
