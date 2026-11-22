<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Partner portal team: staff logins of a Food & Shops partner (own email and password),
 * with a role (manager | sales) and the permissions the owner gave. Prices, payout
 * details and the team stay with the owner. Their actions are logged.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('store_staff')) {
            Schema::create('store_staff', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('store_id')->index();          // the brand
                $table->uuid('user_id')->unique();          // a partner login of its own
                $table->string('role', 20)->default('sales');
                $table->json('permissions')->nullable();
                $table->string('status', 20)->default('active'); // active | removed
                $table->timestamp('ended_at')->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('store_staff_activity')) {
            Schema::create('store_staff_activity', function (Blueprint $table) {
                $table->id();
                $table->uuid('store_id');
                $table->uuid('user_id');
                $table->string('action', 60);
                $table->string('subject', 120)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['store_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('store_staff_activity');
        Schema::dropIfExists('store_staff');
    }
};
