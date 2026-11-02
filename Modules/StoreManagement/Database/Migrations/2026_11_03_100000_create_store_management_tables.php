<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Food & Shops: partner stores, their menus, customer orders and partner
 * payouts. An order is delivered as an ordinary parcel trip (trip_request_id)
 * in the seeded "Food & shop order" parcel category.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('stores')) {
            Schema::create('stores', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('user_id')->nullable()->index();
                $table->string('name', 120);
                $table->string('slug', 140)->unique();
                $table->string('type', 20)->default('restaurant');
                $table->string('phone', 25);
                $table->string('whatsapp', 25)->nullable();
                $table->string('address');
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->foreignUuid('zone_id')->nullable()->index();
                $table->string('logo')->nullable();
                $table->string('cover')->nullable();
                $table->text('description')->nullable();
                $table->json('opening_hours')->nullable();
                $table->boolean('is_open')->default(1);
                $table->unsignedSmallInteger('default_prep_minutes')->default(20);
                $table->decimal('min_order_amount', 24, 2)->default(0);
                $table->boolean('is_active')->default(0);
                $table->integer('sort')->default(0);
                $table->string('payout_method', 30)->nullable();
                $table->string('payout_number', 40)->nullable();
                $table->string('payout_name', 120)->nullable();
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('store_menu_categories')) {
            Schema::create('store_menu_categories', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('store_id')->index();
                $table->string('name', 80);
                $table->integer('sort')->default(0);
                $table->boolean('is_active')->default(1);
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('store_items')) {
            Schema::create('store_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('store_id')->index();
                $table->foreignUuid('store_menu_category_id')->nullable()->index();
                $table->string('name', 120);
                $table->text('description')->nullable();
                $table->decimal('price', 24, 2);
                $table->string('image')->nullable();
                $table->boolean('is_available')->default(1);
                $table->integer('sort')->default(0);
                $table->softDeletes();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('store_orders')) {
            Schema::create('store_orders', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->unsignedBigInteger('ref_id')->unique();
                $table->foreignUuid('customer_id')->index();
                $table->foreignUuid('store_id')->index();
                $table->foreignUuid('zone_id')->nullable();
                $table->string('status', 30)->index();
                $table->decimal('items_subtotal', 24, 2);
                $table->decimal('delivery_fee', 24, 2);
                $table->decimal('total', 24, 2);
                $table->string('payment_method', 30)->nullable();
                $table->string('payment_status', 20)->default('unpaid');
                $table->uuid('payment_request_id')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->decimal('refunded_amount', 24, 2)->default(0);
                $table->timestamp('refunded_at')->nullable();
                $table->string('drop_address');
                $table->decimal('drop_latitude', 10, 7);
                $table->decimal('drop_longitude', 10, 7);
                $table->string('receiver_name', 120);
                $table->string('receiver_phone', 25);
                $table->string('note', 500)->nullable();
                // Route, fare and category used to create the delivery trip.
                $table->json('delivery_quote')->nullable();
                $table->timestamp('quote_expires_at')->nullable();
                $table->unsignedSmallInteger('prep_minutes')->nullable();
                $table->timestamp('ready_at')->nullable();
                $table->foreignUuid('trip_request_id')->nullable()->index();
                $table->unsignedTinyInteger('rider_attempts')->default(0);
                $table->decimal('partner_earning', 24, 2)->default(0);
                $table->timestamp('placed_at')->nullable();
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('rider_requested_at')->nullable();
                $table->timestamp('picked_up_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->string('cancelled_by', 20)->nullable();
                $table->string('cancel_reason')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('store_order_items')) {
            Schema::create('store_order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('store_order_id')->index();
                $table->foreignUuid('store_item_id')->nullable();
                $table->string('name', 120);
                $table->decimal('unit_price', 24, 2);
                $table->unsignedSmallInteger('quantity');
                $table->decimal('line_total', 24, 2);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('store_payouts')) {
            Schema::create('store_payouts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('store_id')->index();
                $table->decimal('amount', 24, 2);
                $table->string('method', 30)->nullable();
                $table->string('reference', 100)->nullable();
                $table->string('note')->nullable();
                $table->foreignUuid('created_by')->nullable();
                $table->timestamps();
            });
        }

        // The parcel category every food and shop delivery is booked under. Its
        // fare is set per zone in the existing Parcel Fare setup.
        $image = 'food-order.png';
        $source = module_path('StoreManagement', 'Resources/assets/' . $image);
        if (is_file($source) && !Storage::disk('public')->exists('parcel/category/' . $image)) {
            Storage::disk('public')->put('parcel/category/' . $image, file_get_contents($source));
        }
        $categoryId = DB::table('parcel_categories')->where('name', 'Food & shop order')->value('id');
        if (!$categoryId) {
            $categoryId = (string)Str::uuid();
            DB::table('parcel_categories')->insert([
                'id' => $categoryId,
                'name' => 'Food & shop order',
                'description' => 'Orders from E-Ride partner restaurants and shops. Prepaid: never collect money.',
                'image' => $image,
                'is_active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        insertBusinessSetting(keyName: 'store_ordering_status', settingType: STORE_SETTINGS, value: '0');
        insertBusinessSetting(keyName: 'store_service_label', settingType: STORE_SETTINGS, value: 'Food & Shops');
        insertBusinessSetting(keyName: 'store_partner_response_minutes', settingType: STORE_SETTINGS, value: '5');
        insertBusinessSetting(keyName: 'store_rider_lead_minutes', settingType: STORE_SETTINGS, value: '10');
        insertBusinessSetting(keyName: 'store_order_parcel_weight', settingType: STORE_SETTINGS, value: '1');
        insertBusinessSetting(keyName: 'store_order_parcel_category_id', settingType: STORE_SETTINGS, value: $categoryId);
    }

    public function down(): void
    {
        Schema::dropIfExists('store_payouts');
        Schema::dropIfExists('store_order_items');
        Schema::dropIfExists('store_orders');
        Schema::dropIfExists('store_items');
        Schema::dropIfExists('store_menu_categories');
        Schema::dropIfExists('stores');
    }
};
