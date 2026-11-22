<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// What a customer sees about a vehicle category before booking: seats, bags,
// features, and example photos of the kind of vehicle.
return new class extends Migration {
    public function up(): void
    {
        Schema::table('vehicle_categories', function (Blueprint $table) {
            if (!Schema::hasColumn('vehicle_categories', 'seats')) {
                $table->unsignedTinyInteger('seats')->nullable()->after('type');
                $table->unsignedTinyInteger('bags')->nullable()->after('seats');
                $table->json('features')->nullable()->after('bags');
                $table->json('gallery')->nullable()->after('features');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_categories', function (Blueprint $table) {
            $table->dropColumn(['seats', 'bags', 'features', 'gallery']);
        });
    }
};
