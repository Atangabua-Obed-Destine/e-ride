<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Homes & spaces, phase 2: reservation money. A seeker whose visit was accepted
 * pays the owner's reservation amount to E-Ride; E-Ride holds it until the
 * move-in, then pays the owner (minus E-Ride's fee) through the payout queue,
 * or refunds the seeker. See ListingReservationService.
 */
return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('listings') && !Schema::hasColumn('listings', 'reservations_on')) {
            Schema::table('listings', function (Blueprint $table) {
                $table->boolean('reservations_on')->default(0)->after('amenities');
                $table->decimal('reservation_amount', 24, 2)->nullable()->after('reservations_on');
                $table->string('payout_phone', 20)->nullable()->after('reservation_amount');
                $table->string('payout_name', 80)->nullable()->after('payout_phone');
            });
        }

        if (!Schema::hasTable('listing_reservations')) {
            Schema::create('listing_reservations', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('listing_id')->index();
                $table->foreignUuid('visit_id')->index();
                $table->foreignUuid('seeker_id')->index();
                $table->foreignUuid('owner_id')->index();
                $table->decimal('amount', 24, 2);
                $table->decimal('fee', 24, 2)->default(0);           // E-Ride's, taken from the owner's share
                $table->decimal('owner_amount', 24, 2)->default(0);  // what the owner is paid
                $table->decimal('refunded_amount', 24, 2)->default(0);
                // awaiting | held | reserved | problem | released | refunded | lapsed
                $table->string('status', 12)->default('awaiting')->index();
                $table->string('payment_method', 30)->nullable();
                $table->foreignUuid('payment_request_id')->nullable();
                $table->date('move_in_date')->nullable();
                $table->timestamp('pay_due_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamp('accept_due_at')->nullable();
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('release_due_at')->nullable();
                $table->timestamp('settled_at')->nullable();
                $table->timestamp('move_in_pushed_at')->nullable();
                $table->timestamp('move_in_reminded_at')->nullable();
                $table->string('problem_by', 10)->nullable();        // seeker | owner
                $table->string('problem_reason', 30)->nullable();    // ListingReservation::PROBLEMS
                $table->text('problem_note')->nullable();
                $table->string('problem_from', 12)->nullable();      // the status before the problem
                $table->foreignUuid('decided_by')->nullable();
                $table->text('decision_note')->nullable();
                $table->string('closed_reason', 40)->nullable();     // why it ended: declined, no_answer, cancelled, ...
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('listing_payouts')) {
            // Money E-Ride sends by mobile money, by hand until Fapshi payouts are enabled.
            Schema::create('listing_payouts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('user_id')->index();
                $table->foreignUuid('reservation_id')->nullable()->index();
                $table->string('kind', 12);                          // owner | refund
                $table->decimal('amount', 24, 2);
                $table->string('phone', 20);
                $table->string('name', 80)->nullable();
                $table->string('status', 10)->default('pending')->index(); // pending | paid
                $table->string('reference', 60)->nullable();
                $table->foreignUuid('paid_by')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_payouts');
        Schema::dropIfExists('listing_reservations');
        if (Schema::hasColumn('listings', 'reservations_on')) {
            Schema::table('listings', fn(Blueprint $table) => $table->dropColumn(['reservations_on', 'reservation_amount', 'payout_phone', 'payout_name']));
        }
    }
};
