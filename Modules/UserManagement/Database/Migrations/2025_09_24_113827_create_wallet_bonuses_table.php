<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wallet_bonuses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 80)->nullable();
            $table->string('description', 255)->nullable();
            $table->decimal('bonus_amount')->default(0);
            $table->string('amount_type',15)->default('amount');
            $table->decimal('min_add_amount')->default(0);
            $table->decimal('max_bonus_amount')->default(0);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->json('user_type');
            $table->boolean('is_active')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_bonuses');
    }
};
