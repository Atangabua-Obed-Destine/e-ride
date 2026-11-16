<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a notification is about (the chat, order, job, place...), so the
 * notification list can open the same screen the push opens.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('app_notifications', 'data')) {
            Schema::table('app_notifications', function (Blueprint $table) {
                $table->json('data')->nullable()->after('action');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('app_notifications', 'data')) {
            Schema::table('app_notifications', function (Blueprint $table) {
                $table->dropColumn('data');
            });
        }
    }
};
