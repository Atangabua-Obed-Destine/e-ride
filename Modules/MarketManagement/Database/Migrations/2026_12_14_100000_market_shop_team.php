<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shop team: people a Market shop owner lets work for the shop (answer chats, handle
 * orders...), each with their own E-Ride account, a role (manager | sales) and the
 * permissions the owner gave. Prices, posting and deleting items, the shop's details and
 * the team stay with the owner. Every action a staff member takes is logged.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('market_shop_staff')) {
            Schema::create('market_shop_staff', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('shop_id')->index();
                $table->uuid('user_id')->index();
                $table->string('role', 20)->default('sales');      // manager | sales
                $table->json('permissions')->nullable();
                $table->string('status', 20)->default('invited');  // invited | active | declined | left | removed
                $table->boolean('on_duty')->default(true);
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('ended_at')->nullable();
                $table->timestamps();
                $table->unique(['shop_id', 'user_id']);
            });
        }
        if (!Schema::hasTable('market_shop_activity')) {
            Schema::create('market_shop_activity', function (Blueprint $table) {
                $table->id();
                $table->uuid('shop_id');
                $table->uuid('user_id');
                $table->string('action', 60);
                $table->string('subject', 120)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['shop_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('market_shop_activity');
        Schema::dropIfExists('market_shop_staff');
    }
};
