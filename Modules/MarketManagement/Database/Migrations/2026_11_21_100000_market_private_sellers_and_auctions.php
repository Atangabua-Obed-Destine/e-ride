<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E-Ride Market: people selling things they own (a "private" seller, no shop), shops shown
 * on the map, and auctions (bids, a winner who gets a normal order, strikes for no-shows).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('market_shops', function (Blueprint $table) {
            // shop | private (a person selling things they own)
            $table->string('kind', 10)->default('shop')->after('user_id')->index();
            // Shops show their pickup point on the map (pin, address, directions); private sellers never.
            $table->boolean('map_visible')->default(true)->after('pickup_address');
            $table->string('address_note', 191)->nullable()->after('map_visible');
        });

        Schema::table('market_items', function (Blueprint $table) {
            // fixed | offers | auction
            $table->string('sale_type', 10)->default('fixed')->after('negotiable');
            // Used items: like_new | good | fair | parts
            $table->string('used_grade', 10)->nullable()->after('condition');
        });

        Schema::create('market_auctions', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('item_id')->index();
            $table->foreignUuid('seller_id')->index();
            $table->decimal('start_price', 14, 2);
            $table->decimal('step', 14, 2);
            $table->decimal('reserve_price', 14, 2)->nullable();
            // Nullable on purpose: MariaDB gives the first NOT NULL timestamp "ON UPDATE CURRENT_TIMESTAMP".
            $table->timestamp('ends_at')->nullable()->index();
            // live | sold | unsold | cancelled
            $table->string('status', 10)->default('live')->index();
            $table->decimal('current_bid', 14, 2)->nullable();
            $table->unsignedInteger('bid_count')->default(0);
            $table->foreignUuid('leader_id')->nullable();
            $table->foreignUuid('winner_id')->nullable();
            $table->decimal('winner_bid', 14, 2)->nullable();
            $table->foreignUuid('order_id')->nullable();
            // The seller offered it to another bidder after a no-show.
            $table->foreignUuid('offered_to')->nullable();
            $table->decimal('offered_price', 14, 2)->nullable();
            $table->timestamp('offer_expires_at')->nullable();
            $table->boolean('ending_notified')->default(false);
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('market_bids', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('auction_id')->index();
            $table->foreignUuid('user_id')->index();
            $table->decimal('amount', 14, 2);
            $table->timestamps();
        });

        Schema::create('market_strikes', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('user_id')->index();
            $table->unsignedBigInteger('auction_id')->nullable();
            $table->string('reason', 30);
            $table->timestamps();
        });

        Schema::table('market_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('auction_id')->nullable()->after('conversation_id');
            // Auction orders: the winner arranges payment with the seller before this time.
            $table->timestamp('payment_due_at')->nullable()->after('agree_by');
            $table->timestamp('payment_arranged_at')->nullable()->after('payment_due_at');
        });
    }

    public function down(): void
    {
        Schema::table('market_orders', function (Blueprint $table) {
            $table->dropColumn(['auction_id', 'payment_due_at', 'payment_arranged_at']);
        });
        Schema::dropIfExists('market_strikes');
        Schema::dropIfExists('market_bids');
        Schema::dropIfExists('market_auctions');
        Schema::table('market_items', function (Blueprint $table) {
            $table->dropColumn(['sale_type', 'used_grade']);
        });
        Schema::table('market_shops', function (Blueprint $table) {
            $table->dropColumn(['kind', 'map_visible', 'address_note']);
        });
    }
};
