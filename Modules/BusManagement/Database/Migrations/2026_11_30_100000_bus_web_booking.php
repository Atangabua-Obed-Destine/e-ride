<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bus booking on the web (eride.../bus): which bookings came from the website, where their messages go
 * (WhatsApp first, SMS as backup), the travel-day reminder, and a log of every message sent.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('bus_bookings', function (Blueprint $table) {
            if (!Schema::hasColumn('bus_bookings', 'via')) {
                // web: booked on the website (source stays "app": online, app commission).
                $table->string('via', 8)->nullable()->after('source')->index();
            }
            if (!Schema::hasColumn('bus_bookings', 'notify_channel')) {
                // whatsapp | sms: where the website traveller gets updates.
                $table->string('notify_channel', 10)->nullable()->after('via');
            }
            if (!Schema::hasColumn('bus_bookings', 'reminded_at')) {
                $table->timestamp('reminded_at')->nullable()->after('collect_reminded_at');
            }
        });

        if (!Schema::hasTable('bus_messages')) {
            Schema::create('bus_messages', function (Blueprint $table) {
                $table->id();
                $table->uuid('booking_id')->index();
                // requested | accepted | paid | reminder | not_booked | update
                $table->string('event', 40);
                // One message per booking and dedupe key, whatever retries or ticks happen.
                $table->string('dedupe', 80);
                // whatsapp | sms
                $table->string('channel', 10);
                $table->string('to', 30);
                // sent | delivered | read | failed | skipped
                $table->string('status', 12);
                $table->string('provider_id', 120)->nullable()->index();
                $table->string('error', 255)->nullable();
                // The SMS sent because this WhatsApp message failed.
                $table->unsignedBigInteger('fallback_id')->nullable();
                $table->text('body')->nullable();
                $table->timestamps();
                $table->unique(['booking_id', 'dedupe', 'channel']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bus_messages');
        Schema::table('bus_bookings', function (Blueprint $table) {
            foreach (['via', 'notify_channel', 'reminded_at'] as $c) {
                if (Schema::hasColumn('bus_bookings', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
