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
        Schema::table('driver_details', function (Blueprint $table) {
            $table->unsignedTinyInteger('is_verified')->default(0)->after('parcel_count');
            $table->string('base_image')->nullable()->after('is_verified');
            $table->string('verified_image')->nullable()->after('base_image');
            $table->unsignedTinyInteger('is_suspended')->default(0)->after('verified_image');
            $table->string('suspend_reason')->nullable()->after('is_suspended');
            $table->dateTime('trigger_verification_at')->nullable()->after('suspend_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('driver_details', function (Blueprint $table) {
            $table->dropColumn(['is_verified', 'base_image', 'verified_image', 'is_suspended', 'suspend_reason', 'trigger_verification_at']);
        });
    }
};
