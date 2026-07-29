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
        Schema::table('firebase_push_notifications', function (Blueprint $table) {
            $table->string('type')->after('status');
            $table->string('group')->after('type');
            $table->string('action')->nullable()->after('group');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('firebase_push_notifications', function (Blueprint $table) {
            $table->dropColumn(['type', 'group', 'action']);
        });
    }
};
