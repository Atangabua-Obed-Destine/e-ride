<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E-Ride ID Verified: a person's ID card or passport, with a selfie, checked once by E-Ride
 * (staff review or automatic face match). Used across E-Ride (bus bookings first).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('id_verifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index();
            // id_card | passport
            $table->string('doc_type', 10);
            $table->string('doc_number', 40);
            $table->string('full_name', 120);
            $table->date('expires_on')->nullable();
            // Private files (local disk), never public.
            $table->string('front_path');
            $table->string('back_path')->nullable();
            $table->string('selfie_path');
            // pending | verified | refused | ended
            $table->string('status', 10)->default('pending');
            // staff | auto
            $table->string('method', 6)->nullable();
            $table->decimal('similarity', 5, 2)->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('refuse_reason', 300)->nullable();
            // expired | name_changed | replaced
            $table->string('end_reason', 20)->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('id_verifications');
    }
};
