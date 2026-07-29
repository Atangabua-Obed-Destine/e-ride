<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('blogs', 'readable_id')) {
            return;
        }

        DB::statement('ALTER TABLE blogs MODIFY readable_id INT UNSIGNED NOT NULL AUTO_INCREMENT');

        // The previous observer used 1000 as the base, so an empty table starts at 1001.
        $nextReadableId = (int) DB::table('blogs')->max('readable_id') + 1;
        $nextReadableId = max($nextReadableId, 1001);

        DB::statement("ALTER TABLE blogs AUTO_INCREMENT = {$nextReadableId}");
    }

    public function down(): void
    {
        if (!Schema::hasColumn('blogs', 'readable_id')) {
            return;
        }

        DB::statement('ALTER TABLE blogs MODIFY readable_id INT UNSIGNED NOT NULL');
    }
};
