<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E-Ride Market: any rider opens a shop, posts items (physical or digital) and
 * shares one link on WhatsApp. Buyers chat with the seller in the app and they
 * arrange payment between them: E-Ride never holds money here.
 * Separate from Food & Shops (StoreManagement), which stays for E-Ride Partners.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('market_shops')) {
            Schema::create('market_shops', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('user_id')->unique();               // one shop per rider
                $table->string('name', 80);
                $table->string('slug', 90)->unique();                   // [domain]/s/<slug>
                $table->string('category', 30)->nullable();
                $table->string('about', 600)->nullable();
                $table->string('area_label', 80)->nullable();           // shown: "Nkwen, Bamenda"
                $table->decimal('pickup_lat', 10, 7)->nullable();       // private: E-Ride delivery only
                $table->decimal('pickup_lng', 10, 7)->nullable();
                $table->string('pickup_address', 191)->nullable();
                $table->string('logo')->nullable();
                $table->string('status', 20)->default('live')->index(); // live | closed
                $table->string('closed_reason', 191)->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamp('verification_requested_at')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->foreignUuid('verified_by')->nullable();
                $table->unsignedInteger('visits')->default(0);
                $table->unsignedInteger('chats')->default(0);
                $table->unsignedInteger('new_users')->default(0);       // installs its links brought
                $table->timestamp('followers_notified_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('market_items')) {
            Schema::create('market_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('shop_id')->index();
                $table->string('code', 12)->unique();                   // [domain]/i/<code>
                $table->string('title', 120);
                $table->text('description')->nullable();
                $table->decimal('price', 12, 2)->nullable();            // null: ask the seller
                $table->boolean('negotiable')->default(false);
                $table->string('condition', 10)->default('new');        // new | used | digital
                $table->string('digital_kind', 20)->nullable();
                $table->string('category', 30)->nullable()->index();
                $table->unsignedInteger('quantity')->default(1);
                $table->boolean('pickup')->default(true);
                $table->boolean('eride_delivery')->default(true);
                $table->boolean('seller_delivers')->default(false);
                // live | hidden (checked by E-Ride) | blocked (banned word) | removed | sold_out
                $table->string('status', 20)->default('live')->index();
                $table->string('blocked_word', 80)->nullable();
                $table->string('status_note', 191)->nullable();
                $table->unsignedInteger('report_count')->default(0);
                $table->unsignedInteger('visits')->default(0);
                $table->unsignedInteger('chats')->default(0);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('market_item_photos')) {
            Schema::create('market_item_photos', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('item_id')->index();
                $table->string('file');
                $table->boolean('preview')->default(false);            // a digital product's free preview page
                $table->unsignedSmallInteger('sort')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('market_conversations')) {
            Schema::create('market_conversations', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('item_id')->index();
                $table->foreignUuid('shop_id')->index();
                $table->foreignUuid('buyer_id')->index();
                $table->foreignUuid('seller_id')->index();
                $table->timestamp('last_message_at')->nullable()->index();
                $table->string('last_message', 191)->nullable();
                $table->unsignedInteger('buyer_unread')->default(0);
                $table->unsignedInteger('seller_unread')->default(0);
                $table->boolean('buyer_blocked')->default(false);      // the seller stopped this buyer
                $table->timestamps();
                $table->unique(['item_id', 'buyer_id']);
            });
        }

        if (!Schema::hasTable('market_messages')) {
            Schema::create('market_messages', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('conversation_id')->index();
                $table->foreignUuid('sender_id');
                $table->string('type', 20)->default('text');            // text | photo | delivery_point | delivery_booked
                $table->text('body')->nullable();
                $table->string('photo')->nullable();
                $table->json('meta')->nullable();                       // point: lat, lng, address; booked: trip id
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('market_reports')) {
            Schema::create('market_reports', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('item_id')->nullable()->index();
                $table->foreignUuid('shop_id')->index();
                $table->foreignUuid('reporter_id')->index();
                $table->string('reason', 20);                           // scam | fake | banned | stolen | other
                $table->string('note', 600)->nullable();
                $table->string('status', 20)->default('open')->index(); // open | kept | hidden | removed | closed
                $table->foreignUuid('decided_by')->nullable();
                $table->timestamp('decided_at')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('market_follows')) {
            Schema::create('market_follows', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('user_id');
                $table->foreignUuid('shop_id')->index();
                $table->timestamps();
                $table->unique(['user_id', 'shop_id']);
            });
        }

        if (!Schema::hasTable('market_blocks')) {
            Schema::create('market_blocks', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('user_id');                         // the buyer who blocked
                $table->foreignUuid('shop_id');
                $table->timestamps();
                $table->unique(['user_id', 'shop_id']);
            });
        }

        if (!Schema::hasTable('market_visits')) {
            Schema::create('market_visits', function (Blueprint $table) {
                $table->id();
                $table->date('day');
                $table->string('subject', 10);                          // item | shop
                $table->uuid('subject_id');
                $table->char('visitor', 40);                            // hashed IP or user id: one a day
                $table->unique(['day', 'subject', 'subject_id', 'visitor'], 'market_visits_once_a_day');
            });
        }

        if (!Schema::hasTable('market_referrals')) {
            Schema::create('market_referrals', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('user_id')->unique();               // the new rider, counted once
                $table->foreignUuid('shop_id')->index();
                $table->foreignUuid('item_id')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        foreach (['market_referrals', 'market_visits', 'market_blocks', 'market_follows', 'market_reports',
                     'market_messages', 'market_conversations', 'market_item_photos', 'market_items', 'market_shops'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
