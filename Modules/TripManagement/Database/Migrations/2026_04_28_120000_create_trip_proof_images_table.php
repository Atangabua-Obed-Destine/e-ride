<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('trip_proof_images', function (Blueprint $table) {
            $table->uuid('id')->primary()->index();
            $table->foreignUuid('trip_id')->unique();
            $table->json('pickup_proof_images')->nullable();
            $table->json('delivery_proof_images')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_proof_images');
    }
};
