<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('firebase_push_notifications')->insert([
            'name' => 'digital_payment_successful',
            'value' => '{paidAmount} Payment to admin is successful.',
            'dynamic_values' => json_encode(['{paidAmount}']),
            'status' => 1,
            'type' => 'others',
            'group' => 'fund',
            'action'=> 'digital_payment_successful',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('firebase_push_notifications')->where(['name' => 'digital_payment_successful', 'group' => 'fund', 'type' => 'others'])->delete();
    }
};
