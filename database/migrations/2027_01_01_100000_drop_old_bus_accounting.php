<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * The first bus accounting build (bus_acc_*) was replaced by the accounting module (App\Ledger,
 * /partner/accounting) on 2026-11-25. Its tables held demo data only; the owner reviewed the new
 * module and approved removing them. bus_money_moves stays: the new books post from it.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach (['bus_acc_cleared', 'bus_acc_recognized', 'bus_acc_months', 'bus_acc_documents', 'bus_acc_lines', 'bus_acc_entries',
            'bus_acc_payslips', 'bus_acc_employees', 'bus_acc_budgets', 'bus_acc_assets', 'bus_acc_years', 'bus_acc_accounts', 'bus_acc_books'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Not brought back: the old build's code is gone.
    }
};
