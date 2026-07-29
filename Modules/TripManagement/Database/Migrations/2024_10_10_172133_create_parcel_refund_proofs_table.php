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
        Schema::create('parcel_refund_proofs', function (Blueprint $table) {
            $table->uuid('id')->primary()->index();
            $table->foreignUuid('parcel_refund_id');
            $table->string('attachment');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parcel_refund_proofs');
    }
};
