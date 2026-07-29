<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Fetch all zones in the order they were created
        $zones = DB::table('zones')->orderBy('id')->get();

        $count = 1;

        foreach ($zones as $zone) {
            // Generate a readable_id using the count
            $readableId = $count;

            // Update the zone with the new readable_id
            DB::table('zones')
                ->where('id', $zone->id)
                ->update(['readable_id' => $readableId]);

            $count++;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('zones')->update(['readable_id' => null]);
    }
};
