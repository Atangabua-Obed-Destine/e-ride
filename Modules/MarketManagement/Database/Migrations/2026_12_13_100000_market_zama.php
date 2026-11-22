<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zama: reselling someone else's goods at your own price.
 *
 * The owner opens an item to zama with the price they want for it (zama_price, seen by
 * resellers only), and may keep it off the public Market (zama_only). A reseller adds it
 * to their own shop: a copy (zama_of = the owner's item) with the owner's photos and the
 * reseller's price, or none ("Ask for price"). Buyer and reseller agree in the chat with
 * private quotes; the deal between reseller and owner is a market_zama_deals row.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('market_items', function (Blueprint $table) {
            if (!Schema::hasColumn('market_items', 'zama_open')) {
                $table->boolean('zama_open')->default(false)->after('service_kind');
                $table->decimal('zama_price', 12, 2)->nullable()->after('zama_open');
                $table->boolean('zama_only')->default(false)->after('zama_price');
                $table->uuid('zama_of')->nullable()->after('zama_only')->index();
            }
        });

        Schema::table('market_conversations', function (Blueprint $table) {
            if (!Schema::hasColumn('market_conversations', 'zama')) {
                // A reseller's chat with the owner of an item they resell (the buyer never sees it).
                $table->boolean('zama')->default(false)->after('buyer_blocked');
            }
        });

        if (!Schema::hasTable('market_zama_deals')) {
            Schema::create('market_zama_deals', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('code', 10)->index();              // the buyer's code at the shop
                $table->uuid('copy_id')->index();                 // the reseller's item
                $table->uuid('source_id')->index();               // the owner's item
                $table->uuid('owner_shop_id')->index();
                $table->uuid('owner_id')->index();
                $table->uuid('reseller_id')->index();
                $table->uuid('buyer_id')->nullable()->index();
                $table->uuid('conversation_id')->nullable();      // buyer and reseller
                $table->uuid('owner_conversation_id')->nullable(); // reseller and owner
                $table->uuid('quote_message_id')->nullable();
                $table->unsignedInteger('quantity')->default(1);
                $table->string('colour', 40)->nullable();
                $table->string('size', 20)->nullable();
                $table->decimal('price', 12, 2);                  // agreed with the buyer, for all pieces
                $table->decimal('owner_price', 12, 2);            // what the owner gets, for all pieces
                $table->decimal('margin', 12, 2)->default(0);     // the reseller's zama
                $table->string('way', 20);                        // deliver | send_buyer
                $table->string('status', 20)->default('asked');   // asked | kept | done | declined | released | cancelled
                $table->string('close_reason', 191)->nullable();
                $table->boolean('stock_held')->default(false);
                $table->timestamp('kept_at')->nullable();
                $table->timestamp('kept_until')->nullable();
                $table->timestamp('done_at')->nullable();
                $table->timestamp('buyer_bought_at')->nullable();
                // The buyer paid the owner: the owner owes the reseller the margin.
                $table->string('margin_status', 20)->default('none'); // none | owed | paid | received
                $table->timestamp('margin_paid_at')->nullable();
                $table->timestamp('margin_received_at')->nullable();
                $table->timestamp('margin_reminded_at')->nullable();
                $table->timestamps();
                $table->index(['owner_id', 'status']);
                $table->index(['reseller_id', 'status']);
            });
        }

        if (!Schema::hasTable('market_zama_removed')) {
            // Resellers an owner no longer lets sell their items.
            Schema::create('market_zama_removed', function (Blueprint $table) {
                $table->id();
                $table->uuid('owner_shop_id');
                $table->uuid('reseller_id');
                $table->timestamps();
                $table->unique(['owner_shop_id', 'reseller_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('market_zama_removed');
        Schema::dropIfExists('market_zama_deals');
        if (Schema::hasColumn('market_conversations', 'zama')) {
            Schema::table('market_conversations', fn(Blueprint $table) => $table->dropColumn('zama'));
        }
        if (Schema::hasColumn('market_items', 'zama_open')) {
            Schema::table('market_items', fn(Blueprint $table) => $table->dropColumn(['zama_open', 'zama_price', 'zama_only', 'zama_of']));
        }
    }
};
