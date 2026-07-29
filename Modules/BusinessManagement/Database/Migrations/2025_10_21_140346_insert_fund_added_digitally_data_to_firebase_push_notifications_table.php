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
            'name' => 'fund_added_digitally',
            'value' => '{totalAmount} is added to your wallet.',
            'dynamic_values' => json_encode(['{paidAmount}, {bonusAmount}, {totalAmount}']),
            'status' => 1,
            'type' => 'others',
            'group' => 'fund',
            'action'=> 'fund_added_digitally',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('firebase_push_notifications')->where(['name' => 'fund_added_digitally', 'group' => 'fund', 'type' => 'others'])->delete();
    }
};
