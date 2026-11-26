<?php

use App\Ledger\Ledger;
use App\Ledger\Models\ChartOfAccount;
use App\Ledger\Models\DefaultAccountMapping;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Books opened before 2026-11-26 get what new books now start with:
 *  - a "Bank transfer" payment method (521) for spending, staff pay and suppliers paid by bank;
 *  - "Office cash" (571): cash paid or received at the office with no payment account named,
 *    so it no longer lands in a counter agent's till (5712);
 *  - ticket money, E-Ride statements paid and till counts already posted, written into the
 *    payment accounts' books (App\Ledger\ERide\BusBook), so their Current balance is the money there.
 */
return new class extends Migration {
    public function up(): void
    {
        foreach (DB::table('ledger_businesses')->pluck('id') as $bid) {
            if (! Ledger::isSetUp((int) $bid)) {
                continue;
            }
            Ledger::forBusiness((int) $bid, function () use ($bid) {
                foreach ([['payment_method', 'bank', '521'], ['office_cash', '', '571']] as [$type, $key, $code]) {
                    if (DefaultAccountMapping::where('mapping_type', $type)->where('category_key', $key)->exists()) {
                        continue;
                    }
                    $id = ChartOfAccount::where('account_code', $code)->value('id');
                    if ($id) {
                        DefaultAccountMapping::create(['business_id' => (int) $bid, 'mapping_type' => $type, 'category_key' => $key,
                            'debit_account_id' => $id, 'credit_account_id' => $id, 'status' => 1]);
                    }
                }
                \App\Ledger\ERide\BusBook::backfill();
            });
        }
    }

    public function down(): void
    {
    }
};
