<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void {
        DB::table('firebase_push_notifications')->where(['name' => 'admin_collected_cash', 'group' => 'fund', 'type' => 'others'])->update(['dynamic_values' => json_encode(['{paidAmount}'])]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
