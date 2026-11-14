<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E-Ride Market orders: a buyer sends a cart to a shop as an order request, the seller
 * confirms each line (or declines), the buyer pays the seller directly (E-Ride never holds
 * the money) and closes the order with a receive code. Completed orders can be rated.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('market_items', function (Blueprint $table) {
            // {"colours":[{"name":"Purple","photo_id":12}],"sizes":["40","41"]}
            $table->json('options')->nullable()->after('quantity');
            // {"Purple|42": 1, "Purple|43": 3}; a dimension the item does not have is empty: "|42", "Purple|"
            $table->json('stock')->nullable()->after('options');
            $table->decimal('wholesale_price', 14, 2)->nullable()->after('price');
            $table->unsignedInteger('wholesale_min')->nullable()->after('wholesale_price');
            $table->unsignedInteger('orders')->default(0)->after('chats');
        });

        Schema::table('market_shops', function (Blueprint $table) {
            $table->boolean('taking_orders')->default(true)->after('status');
            // ["pickup","eride","seller"] and ["cash_delivery","momo_delivery","pickup","before"]
            $table->json('fulfilment')->nullable()->after('taking_orders');
            $table->json('pay_methods')->nullable()->after('fulfilment');
            $table->string('open_from', 5)->nullable()->after('pay_methods');
            $table->string('open_to', 5)->nullable()->after('open_from');
            $table->decimal('min_order', 14, 2)->nullable()->after('open_to');
            $table->decimal('rating', 3, 2)->nullable()->after('new_users');
            $table->unsignedInteger('rating_count')->default(0)->after('rating');
            $table->unsignedInteger('orders_completed')->default(0)->after('rating_count');
            $table->unsignedInteger('reply_minutes')->nullable()->after('orders_completed');
            $table->timestamp('demoted_until')->nullable()->after('reply_minutes');
        });

        Schema::create('market_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 12)->unique();
            $table->foreignUuid('shop_id')->index();
            $table->foreignUuid('buyer_id')->index();
            $table->foreignUuid('seller_id')->index();
            $table->foreignUuid('conversation_id')->nullable();
            $table->string('status', 20)->index();
            $table->string('fulfilment', 10);
            $table->string('pay_method', 20);
            $table->decimal('drop_lat', 10, 7)->nullable();
            $table->decimal('drop_lng', 10, 7)->nullable();
            $table->string('drop_address')->nullable();
            $table->string('note', 500)->nullable();
            $table->decimal('requested_total', 14, 2)->default(0);
            $table->decimal('confirmed_total', 14, 2)->nullable();
            $table->string('receive_code', 4)->nullable();
            $table->string('decline_reason', 30)->nullable();
            $table->string('decline_note', 300)->nullable();
            $table->string('cancel_reason', 300)->nullable();
            $table->string('cancelled_by', 10)->nullable();
            $table->foreignUuid('trip_id')->nullable();
            $table->timestamp('respond_by')->nullable()->index();
            $table->timestamp('agree_by')->nullable()->index();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->boolean('stock_held')->default(false);
            $table->timestamps();
        });

        Schema::create('market_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('order_id')->index();
            $table->foreignUuid('item_id')->index();
            $table->string('title', 120);
            $table->string('photo')->nullable();
            $table->string('colour', 40)->nullable();
            $table->string('size', 20)->nullable();
            $table->decimal('unit_price', 14, 2);
            $table->boolean('wholesale')->default(false);
            $table->unsignedInteger('qty');
            $table->unsignedInteger('qty_confirmed')->nullable();
            $table->string('seller_note', 200)->nullable();
            $table->timestamps();
        });

        Schema::create('market_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('order_id')->unique();
            $table->foreignUuid('shop_id')->index();
            $table->foreignUuid('buyer_id');
            $table->unsignedTinyInteger('stars');
            $table->json('tags')->nullable();
            $table->string('comment', 500)->nullable();
            $table->boolean('hidden')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_reviews');
        Schema::dropIfExists('market_order_lines');
        Schema::dropIfExists('market_orders');
        Schema::table('market_shops', function (Blueprint $table) {
            $table->dropColumn(['taking_orders', 'fulfilment', 'pay_methods', 'open_from', 'open_to', 'min_order', 'rating', 'rating_count',
                'orders_completed', 'reply_minutes', 'demoted_until']);
        });
        Schema::table('market_items', function (Blueprint $table) {
            $table->dropColumn(['options', 'stock', 'wholesale_price', 'wholesale_min', 'orders']);
        });
    }
};
