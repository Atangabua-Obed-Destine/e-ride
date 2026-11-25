<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Staff pay, budgets and VAT for the bus books (branch or head office).
 *
 *  - bus_acc_employees: who is paid (drivers, loaders, counter agents...), their monthly pay and CNPS number.
 *  - bus_acc_payslips: one per person per monthly payroll, with every withholding and the employer's charges.
 *  - bus_acc_budgets: the amount planned per account and month.
 * The new accounts (pay owed, CNPS, State, employer charges, VAT) are added to every chart.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('bus_acc_employees')) {
            Schema::create('bus_acc_employees', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->uuid('agency_id')->index();
                $t->string('name', 120);
                // driver | loader | counter | manager | other
                $t->string('job', 12)->default('other');
                $t->uuid('user_id')->nullable();
                $t->string('cnps_number', 40)->nullable();
                $t->string('niu', 40)->nullable();
                $t->string('phone', 20)->nullable();
                $t->decimal('base_pay', 14, 2)->default(0);
                $t->date('hired_on')->nullable();
                $t->boolean('active')->default(true);
                $t->timestamps();
            });
        }
        if (!Schema::hasTable('bus_acc_payslips')) {
            Schema::create('bus_acc_payslips', function (Blueprint $t) {
                $t->id();
                $t->uuid('agency_id');
                $t->uuid('document_id')->index();
                $t->uuid('employee_id')->index();
                $t->date('month');
                $t->decimal('base', 14, 2)->default(0);
                $t->decimal('extra', 14, 2)->default(0);
                $t->decimal('gross', 14, 2)->default(0);
                $t->decimal('cnps', 14, 2)->default(0);
                $t->decimal('irpp', 14, 2)->default(0);
                $t->decimal('cac', 14, 2)->default(0);
                $t->decimal('cfc', 14, 2)->default(0);
                $t->decimal('crtv', 14, 2)->default(0);
                $t->decimal('tdl', 14, 2)->default(0);
                $t->decimal('net', 14, 2)->default(0);
                $t->decimal('employer_cnps', 14, 2)->default(0);
                $t->decimal('employer_taxes', 14, 2)->default(0);
                $t->json('detail')->nullable();
                $t->timestamp('created_at')->nullable();
                $t->index(['agency_id', 'month']);
            });
        }
        if (!Schema::hasTable('bus_acc_budgets')) {
            Schema::create('bus_acc_budgets', function (Blueprint $t) {
                $t->id();
                $t->uuid('agency_id');
                $t->unsignedSmallInteger('year');
                $t->uuid('account_id');
                $t->unsignedTinyInteger('month');
                $t->decimal('amount', 14, 2)->default(0);
                $t->timestamps();
                $t->unique(['agency_id', 'year', 'account_id', 'month']);
            });
        }
        // Staff salaries (6611) is what a payroll charges: give it its role in the charts already made.
        \Illuminate\Support\Facades\DB::table('bus_acc_accounts')->where('code', '6611')->whereNull('role')->update(['role' => 'salaries']);
        $ledger = app(\Modules\BusManagement\Service\Accounting\Ledger::class);
        foreach (\Modules\BusManagement\Entities\BusAccBook::all() as $books) {
            if ($owner = $books->owner()) {
                $ledger->seedChart($owner);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bus_acc_budgets');
        Schema::dropIfExists('bus_acc_payslips');
        Schema::dropIfExists('bus_acc_employees');
    }
};
