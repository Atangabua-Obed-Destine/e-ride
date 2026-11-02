<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Fapshi (MTN MoMo and Orange Money) as a payment gateway.
 *
 * One fapshi_transactions row per payment link. A payment request can have
 * several (a link expired, the customer tried again), and only one of them can
 * ever settle it: see FapshiPayments::apply().
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('fapshi_transactions')) {
            Schema::create('fapshi_transactions', function (Blueprint $table) {
                $table->id();
                $table->uuid('payment_request_id')->index();
                $table->string('environment', 10);
                $table->string('trans_id', 100)->nullable()->unique();
                $table->string('external_id', 100)->unique();
                $table->decimal('base_amount', 24, 2);
                $table->unsignedInteger('fee_amount')->default(0);
                $table->unsignedInteger('charged_amount');
                $table->unsignedInteger('amount_received')->nullable();
                $table->unsignedInteger('revenue')->nullable();
                $table->string('currency', 3)->default('XAF');
                // created | pending | successful | failed | expired | mismatched | refundable
                $table->string('status', 20)->default('created')->index();
                $table->string('financial_trans_id', 100)->nullable();
                $table->string('medium', 30)->nullable();
                $table->text('payment_link')->nullable();
                $table->string('failure_reason')->nullable();
                $table->json('raw_request')->nullable();
                $table->json('raw_response')->nullable();
                $table->timestamp('initiated_at')->nullable();
                $table->timestamp('last_checked_at')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->timestamps();
            });
        }

        // A neutral mark for the apps' payment list (colours only, no operator
        // logos). storage/ is not in git, so it ships with the module.
        $image = 'fapshi-mobile-money.png';
        $source = module_path('Gateways', 'Resources/assets/' . $image);
        if (is_file($source) && !Storage::disk('public')->exists('payment_modules/gateway_image/' . $image)) {
            Storage::disk('public')->put('payment_modules/gateway_image/' . $image, file_get_contents($source));
        }

        $exists = DB::table('settings')->where('key_name', 'fapshi')->where('settings_type', 'payment_config')->exists();
        if (!$exists) {
            $values = json_encode([
                'gateway' => 'fapshi',
                'mode' => 'test',
                'status' => '0',
                'api_user' => '',
                'api_key' => '',
                'webhook_secret' => '',
                'fee_percent' => '0',
                'fee_fixed' => '0',
            ]);
            DB::table('settings')->insert([
                'id' => (string)Str::uuid(),
                'key_name' => 'fapshi',
                'live_values' => $values,
                'test_values' => $values,
                'settings_type' => 'payment_config',
                'mode' => 'test',
                'is_active' => 0,
                'additional_data' => json_encode(['gateway_title' => 'Mobile Money (MTN & Orange)', 'gateway_image' => $image]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fapshi_transactions');
        DB::table('settings')->where('key_name', 'fapshi')->where('settings_type', 'payment_config')->delete();
    }
};
