<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The POS Innova channel (docs/posinnova-channel-contract.md): a branch linked to its POS Innova
 * system by a key, the products that POS publishes for it, their images, and on each order whether
 * the salesperson rang it up in POS. POS always calls E-Ride; E-Ride never writes into POS.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('store_pos_links')) {
            Schema::create('store_pos_links', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('store_id')->index();                  // the E-Ride kitchen (branch or single store)
                $table->string('key_hash', 64)->unique();           // sha256 of erk_...
                $table->string('key_hint', 12);                     // erk_ + first 4, to recognise it
                $table->unsignedBigInteger('pos_location_id')->nullable();
                $table->string('pos_location_name', 120)->nullable();
                $table->string('pos_version', 40)->nullable();
                $table->string('qty_display', 10)->default('available'); // available | exact
                $table->timestamp('linked_at')->nullable();         // first call from POS
                $table->timestamp('last_seen_at')->nullable();      // any call (E-Ride's clock)
                $table->timestamp('last_heartbeat_at')->nullable();
                $table->timestamp('last_catalog_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->uuid('created_by')->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('store_pos_items')) {
            Schema::create('store_pos_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('link_id')->index();
                $table->unsignedBigInteger('variation_id');
                $table->unsignedBigInteger('product_id')->nullable();
                $table->string('sku', 120)->nullable()->index();
                $table->string('name', 191);
                $table->string('variation_name', 120)->nullable();
                $table->text('description')->nullable();
                $table->string('category', 120)->nullable();
                $table->string('sub_category', 120)->nullable();
                $table->string('image_sha1', 40)->nullable();
                $table->unsignedBigInteger('unit_id')->nullable();
                $table->string('unit', 40)->nullable();
                $table->unsignedInteger('price')->default(0);
                $table->boolean('track_stock')->default(true);
                $table->decimal('qty_available', 16, 4)->nullable();
                $table->decimal('low_stock_threshold', 16, 4)->default(0);
                $table->date('next_expiry')->nullable();
                $table->timestamp('pos_updated_at')->nullable();
                $table->uuid('store_item_id')->nullable()->index(); // the E-Ride menu item it is linked to
                $table->uuid('snapshot_id')->nullable();
                $table->timestamp('removed_at')->nullable();
                $table->timestamps();
                $table->unique(['link_id', 'variation_id']);
            });
        }
        if (!Schema::hasTable('store_pos_images')) {
            Schema::create('store_pos_images', function (Blueprint $table) {
                $table->string('sha1', 40)->primary();
                $table->string('file', 120);
                $table->timestamps();
            });
        }
        Schema::table('store_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('store_orders', 'pos_sale_id')) {
                $table->unsignedBigInteger('pos_sale_id')->nullable();
                $table->string('pos_invoice_no', 60)->nullable();
                $table->timestamp('pos_rung_up_at')->nullable();
                $table->string('pos_rung_by', 60)->nullable();
                $table->unsignedInteger('pos_sale_total')->nullable();
                $table->json('pos_lines')->nullable();
                $table->boolean('pos_matches')->nullable();
                $table->timestamp('pos_cancel_seen_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_pos_images');
        Schema::dropIfExists('store_pos_items');
        Schema::dropIfExists('store_pos_links');
    }
};
