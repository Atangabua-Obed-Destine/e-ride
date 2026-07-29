<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Fetch all transactions in the order they were created
        $transactions = DB::table('transactions')->orderBy('id')->get();

        $count = 10000000; // Starting from 10000000

        foreach ($transactions as $transaction) {
            // Generate a readable_id using the count
            $readableId = $count;

            // Update the transaction with the new readable_id
            DB::table('transactions')
                ->where('id', $transaction->id)
                ->update(['readable_id' => $readableId]);

            $count++;
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('transactions')->update(['readable_id' => null]);
    }
};
