<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resellers for Food & Shops partners: people who share a store's link and earn a
 * commission on the orders it brings. The store sets its rate (stores.reseller_pct,
 * null = off); the commission is on the items only, taken from the store's earning when
 * the order is delivered, and E-Ride pays it by Mobile Money (store_reseller_payouts).
 * A tap on a reseller's link counts for that store for some days (store_reseller_taps).
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('store_resellers')) {
            Schema::create('store_resellers', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->unique();
                $table->string('code', 12)->unique();
                $table->string('momo_phone', 20);
                $table->string('momo_name', 80);
                $table->string('status', 20)->default('active'); // active | paused
                $table->string('pause_reason', 191)->nullable();
                $table->unsignedInteger('taps')->default(0);
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('store_reseller_taps')) {
            Schema::create('store_reseller_taps', function (Blueprint $table) {
                $table->id();
                $table->uuid('reseller_id')->index();
                $table->uuid('customer_id');
                $table->uuid('store_id');                      // the brand
                $table->timestamp('tapped_at');
                $table->index(['customer_id', 'store_id', 'tapped_at']);
            });
        }
        if (!Schema::hasTable('store_reseller_payouts')) {
            Schema::create('store_reseller_payouts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('reseller_id')->index();
                $table->decimal('amount', 12, 2);
                $table->unsignedInteger('orders')->default(0);
                $table->string('reference', 120)->nullable();
                $table->uuid('paid_by')->nullable();
                $table->timestamp('paid_at');
                $table->timestamps();
            });
        }
        Schema::table('stores', function (Blueprint $table) {
            if (!Schema::hasColumn('stores', 'reseller_pct')) {
                $table->decimal('reseller_pct', 5, 2)->nullable();
            }
        });
        Schema::table('store_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('store_orders', 'reseller_id')) {
                $table->uuid('reseller_id')->nullable()->index();
                $table->decimal('reseller_pct', 5, 2)->nullable();
                $table->decimal('reseller_commission', 12, 2)->default(0);
                $table->uuid('reseller_payout_id')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('store_orders', 'reseller_id')) {
            Schema::table('store_orders', fn(Blueprint $table) => $table->dropColumn(['reseller_id', 'reseller_pct', 'reseller_commission', 'reseller_payout_id']));
        }
        if (Schema::hasColumn('stores', 'reseller_pct')) {
            Schema::table('stores', fn(Blueprint $table) => $table->dropColumn('reseller_pct'));
        }
        Schema::dropIfExists('store_reseller_payouts');
        Schema::dropIfExists('store_reseller_taps');
        Schema::dropIfExists('store_resellers');
    }
};
