<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Refunds on a bus booking, kept apart:
 *  - refund_amount: the part of the fare given back (statements subtract it).
 *  - extra_refund: money paid beyond the fare (a late or a second payment),
 *    never part of the fare, so it never touches statements or commission.
 *  - refund_owed: what the agency has to send now. A new refund after one was
 *    sent starts from zero instead of mixing with the one already sent.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('bus_bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('bus_bookings', 'extra_refund')) {
                $table->decimal('extra_refund', 12, 2)->default(0)->after('refund_amount');
            }
            if (!Schema::hasColumn('bus_bookings', 'refund_owed')) {
                $table->decimal('refund_owed', 12, 2)->default(0)->after('extra_refund');
            }
        });
        DB::table('bus_bookings')->where('refund_status', 'due')->update(['refund_owed' => DB::raw('refund_amount')]);
    }

    public function down(): void
    {
        Schema::table('bus_bookings', function (Blueprint $table) {
            $table->dropColumn(['extra_refund', 'refund_owed']);
        });
    }
};
