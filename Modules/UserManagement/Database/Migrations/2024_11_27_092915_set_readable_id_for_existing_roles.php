<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $roles = DB::table('roles')->orderBy('id')->get();
        $count = 1000;

        foreach ($roles as $role) {
            // Generate a readable_id using the count
            $readableId = $count;

            // Update the role with the new readable_id
            DB::table('roles')
                ->where('id', $role->id)
                ->update(['readable_id' => $readableId]);

            $count++;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('roles')->update(['readable_id' => null]);
    }
};
