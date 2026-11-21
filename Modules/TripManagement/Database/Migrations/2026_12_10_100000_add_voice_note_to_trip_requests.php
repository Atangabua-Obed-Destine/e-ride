<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A voice note for the driver: the customer says, in Pidgin, English or
 * French, where to find them (the gate, the shop opposite, the junction).
 * The driver plays it on the request and on the way; the transcript is shown
 * under it for a noisy street.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('trip_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('trip_requests', 'voice_note')) {
                $table->string('voice_note', 120)->nullable()->after('pickup_note');
                $table->unsignedSmallInteger('voice_note_seconds')->nullable()->after('voice_note');
                $table->string('voice_note_text', 500)->nullable()->after('voice_note_seconds');
            }
        });
    }

    public function down(): void
    {
        Schema::table('trip_requests', function (Blueprint $table) {
            if (Schema::hasColumn('trip_requests', 'voice_note')) {
                $table->dropColumn(['voice_note', 'voice_note_seconds', 'voice_note_text']);
            }
        });
    }
};
