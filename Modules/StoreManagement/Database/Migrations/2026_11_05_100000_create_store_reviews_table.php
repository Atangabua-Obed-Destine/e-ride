<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A customer's rating of the food and service of a delivered order. The
 * rider is rated in the existing `reviews` table, against the delivery trip.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('store_reviews')) {
            Schema::create('store_reviews', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('store_order_id')->unique();
                $table->foreignUuid('store_id')->index();
                $table->foreignUuid('customer_id')->index();
                $table->unsignedTinyInteger('rating');
                $table->string('comment', 500)->nullable();
                $table->boolean('is_hidden')->default(0);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('store_reviews');
    }
};
