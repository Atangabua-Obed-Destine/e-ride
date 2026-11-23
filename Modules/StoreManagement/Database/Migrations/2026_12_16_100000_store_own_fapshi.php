<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A business with its own Fapshi account takes its customers' Mobile Money directly (items and
 * delivery). E-Ride holds none of that money and sends a weekly statement of what it is owed
 * (its riders' delivery fees, resellers, any share). A business without its own account keeps
 * the old way: E-Ride collects and pays it out.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            if (!Schema::hasColumn('stores', 'fapshi')) {
                $table->text('fapshi')->nullable();                 // encrypted {api_user, api_key, webhook_secret, mode}
                $table->timestamp('fapshi_ok_at')->nullable();      // keys tested and working
                $table->string('fapshi_code', 24)->nullable()->unique(); // in the webhook URL
                $table->boolean('own_payments')->default(false);    // the owner switched it on
                $table->timestamp('statement_paused_at')->nullable(); // a statement too late: no app orders
            }
        });
        Schema::table('store_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('store_orders', 'paid_to')) {
                $table->string('paid_to', 10)->default('eride');    // eride | shop
                $table->decimal('owed_to_eride', 24, 2)->default(0); // shop-paid orders: what goes on the statement
                $table->uuid('statement_id')->nullable()->index();
                $table->decimal('shop_refund_due', 24, 2)->default(0); // shop-paid: what the shop must send back
                $table->timestamp('shop_refund_sent_at')->nullable();
                $table->string('shop_refund_ref', 80)->nullable();
            }
        });
        if (!Schema::hasTable('store_payments')) {
            Schema::create('store_payments', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('store_order_id')->index();
                $table->uuid('store_id')->index();                  // the brand whose Fapshi took it
                $table->string('purpose', 20)->default('order');    // order | delivery_fee
                $table->string('environment', 10);
                $table->string('external_id', 80)->unique();
                $table->string('trans_id', 80)->nullable()->index();
                $table->unsignedInteger('amount');
                $table->string('status', 20);                       // created | pending | successful | failed | expired | mismatched
                $table->string('medium', 30)->nullable();
                $table->text('payment_link')->nullable();
                $table->json('raw_response')->nullable();
                $table->string('failure_reason')->nullable();
                $table->timestamp('initiated_at')->nullable();
                $table->timestamp('last_checked_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('store_statements')) {
            Schema::create('store_statements', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('store_id')->index();                  // the brand
                $table->date('period_from');
                $table->date('period_to');
                $table->unsignedInteger('orders_count')->default(0);
                $table->decimal('delivery_fees', 24, 2)->default(0);
                $table->decimal('other', 24, 2)->default(0);        // resellers, own-delivery share, store covers
                $table->decimal('amount', 24, 2)->default(0);
                $table->timestamp('due_at');
                $table->string('status', 10)->default('due');       // due | paid
                $table->timestamp('paid_at')->nullable();
                $table->string('reference', 120)->nullable();
                $table->uuid('paid_by')->nullable();
                $table->timestamps();
                $table->unique(['store_id', 'period_from']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('store_statements');
        Schema::dropIfExists('store_payments');
    }
};
