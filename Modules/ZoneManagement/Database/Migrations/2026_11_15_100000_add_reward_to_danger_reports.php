<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A driver earns a reward (Safety map > Settings) when E-Ride confirms their danger
 * report. Kept on the report so it is paid once.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('danger_reports', function (Blueprint $table) {
            if (!Schema::hasColumn('danger_reports', 'reward_amount')) {
                $table->decimal('reward_amount', 24, 2)->nullable()->after('handled_at');
            }
            if (!Schema::hasColumn('danger_reports', 'rewarded_at')) {
                $table->timestamp('rewarded_at')->nullable()->after('reward_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('danger_reports', function (Blueprint $table) {
            foreach (['rewarded_at', 'reward_amount'] as $column) {
                if (Schema::hasColumn('danger_reports', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
