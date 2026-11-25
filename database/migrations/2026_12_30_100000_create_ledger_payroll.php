<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * paxhitestbed's payroll and tax (Human Resource: Payroll, Loans and Advances, Designations,
 * Departments, Allowance and Deduction types, Tax groups, Tax settings, Tax remittance, Pay slip
 * and Payroll settings), per set of books. Its table names, except the staff: paxhitestbed's staff
 * are its users, E-Ride's bus staff are often not app users, so they get their own register,
 * ledger_staff (with an optional link to the E-Ride user). Every *_by column is a user UUID.
 *
 * Left out: hourly pay (it reads attendance, which is not ported), the staff's own payslips screen
 * and payslip emails, and the legacy second bank-account store (one set of pay-to details on the
 * staff record, as paxhitestbed's guide advises for a fresh build).
 */
return new class extends Migration
{
    public function up()
    {
        foreach (['designations', 'departments'] as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->id();
                $t->unsignedInteger('business_id');
                $t->string('title');
                $t->string('slug');
                $t->text('description')->nullable();
                $t->boolean('status')->default(1);
                $t->timestamps();
                $t->index(['business_id', 'status']);
            });
        }

        Schema::create('allowance_types', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->string('title');
            $t->text('description')->nullable();
            $t->boolean('is_taxable')->default(0);
            $t->boolean('status')->default(1);
            $t->timestamps();
            $t->index(['business_id', 'status']);
        });

        Schema::create('deduction_types', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->string('title');
            $t->text('description')->nullable();
            $t->boolean('status')->default(1);
            $t->timestamps();
            $t->index(['business_id', 'status']);
        });

        Schema::create('ledger_staff', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->string('staff_no', 50);                      // paxhitestbed users.staff_id ("Staff ID")
            $t->string('first_name');
            $t->string('last_name')->nullable();
            $t->string('gender', 10)->nullable();            // 1 male, 2 female, 3 other (paxhitestbed's codes)
            $t->string('phone', 30)->nullable();
            $t->string('email')->nullable();
            $t->unsignedBigInteger('designation_id')->nullable();
            $t->unsignedBigInteger('department_id')->nullable();
            $t->unsignedTinyInteger('contract_type')->nullable(); // 1 full time, 2 part time
            $t->unsignedTinyInteger('salary_type')->default(1);   // 1 fixed (2 hourly: not ported)
            $t->decimal('basic_salary', 22, 4)->default(0);
            $t->date('joining_date')->nullable();
            $t->date('ending_date')->nullable();
            // where the pay goes (one store: paxhitestbed's guide §11.6)
            $t->string('pay_channel', 20)->nullable();       // bank, mobile_money, cash
            $t->string('bank_name')->nullable();
            $t->string('bank_branch')->nullable();
            $t->string('account_name')->nullable();
            $t->string('account_number', 60)->nullable();
            $t->string('bank_code', 30)->nullable();
            $t->string('momo_provider', 20)->nullable();     // mtn, orange
            $t->string('momo_number', 30)->nullable();
            $t->string('cnps_no', 50)->nullable();           // paxhitestbed epf_no
            $t->string('niu', 50)->nullable();               // paxhitestbed tin_no (taxpayer number)
            $t->string('user_id', 36)->nullable();           // the E-Ride user, when this person is one
            $t->text('note')->nullable();
            $t->boolean('status')->default(1);
            $t->string('created_by', 36)->nullable();
            $t->string('updated_by', 36)->nullable();
            $t->timestamps();
            $t->unique(['business_id', 'staff_no']);
            $t->index(['business_id', 'status']);
        });

        Schema::create('tax_groups', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->string('title');
            $t->string('code', 50)->nullable();
            $t->string('description', 500)->nullable();
            $t->boolean('is_progressive')->default(0);
            $t->boolean('status')->default(1);
            $t->date('effective_from')->nullable();
            $t->date('effective_to')->nullable();
            $t->unsignedInteger('display_order')->default(0);
            $t->unsignedBigInteger('liability_account_id')->nullable();
            $t->timestamps();
            $t->index(['business_id', 'code']);
        });

        Schema::create('tax_settings', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->unsignedBigInteger('tax_group_id')->nullable();
            $t->boolean('is_dependent')->default(0);
            $t->string('depends_on_type', 20)->nullable();   // tax_group, tax_setting
            $t->unsignedBigInteger('depends_on_id')->nullable();
            $t->string('title')->nullable();
            $t->unsignedInteger('bracket_order')->default(0);
            $t->unsignedTinyInteger('tax_type')->default(1); // 1 percentage, 2 fixed amount
            $t->boolean('is_shared')->default(0);
            $t->string('paid_by', 10)->default('employee');  // employee, employer, both
            $t->decimal('min_amount', 22, 4)->default(0);
            $t->decimal('max_amount', 22, 4)->default(0);
            $t->decimal('percentange', 10, 4)->default(0);   // paxhitestbed's column name, kept
            $t->decimal('employer_percentage', 10, 4)->default(0);
            $t->decimal('fixed_amount', 22, 4)->default(0);
            $t->decimal('employer_fixed_amount', 22, 4)->default(0);
            $t->decimal('max_no_taxable_amount', 22, 4)->default(0);
            $t->boolean('status')->default(1);
            $t->date('effective_from')->nullable();
            $t->date('effective_to')->nullable();
            $t->unsignedBigInteger('liability_account_id')->nullable();
            $t->timestamps();
            $t->index(['business_id', 'tax_group_id']);
        });

        Schema::create('staff_tax_exemptions', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->unsignedBigInteger('staff_id');
            $t->unsignedBigInteger('tax_setting_id');
            $t->string('reason', 500)->nullable();
            $t->decimal('custom_percentage', 10, 4)->nullable();
            $t->decimal('custom_fixed_amount', 22, 4)->nullable();
            $t->date('expires_at')->nullable();
            $t->timestamps();
            $t->unique(['business_id', 'staff_id', 'tax_setting_id'], 'staff_tax_exemptions_unique');
        });

        Schema::create('payroll_settings', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id')->unique();
            $t->boolean('require_approval')->default(0);
            $t->boolean('carry_over_lines')->default(1);
            $t->boolean('prorate_partial_months')->default(0);
            $t->string('proration_basis', 20)->default('calendar_days');
            $t->json('bank_file_columns')->nullable();
            $t->string('bank_file_delimiter', 5)->default(',');
            $t->boolean('bank_file_header')->default(1);
            $t->string('bank_file_reference', 120)->default('SALARY {MONTH} {YEAR}');
            $t->unsignedTinyInteger('bank_file_decimals')->default(0);
            $t->unsignedBigInteger('advance_payment_account_id')->nullable(); // payment account loans are paid out of
            $t->string('updated_by', 36)->nullable();
            $t->timestamps();
        });

        Schema::create('staff_loans', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->unsignedBigInteger('staff_id');
            $t->string('kind', 20)->default('loan');         // loan, advance
            $t->string('title', 100);
            $t->decimal('amount', 22, 4);
            $t->decimal('instalment', 22, 4);
            $t->date('start_month');
            $t->date('given_on');
            $t->unsignedBigInteger('payment_account_id')->nullable(); // E-Ride: the money left this account
            $t->string('status', 20)->default('active');     // active, cancelled
            $t->string('note', 500)->nullable();
            $t->string('created_by', 36)->nullable();
            $t->string('updated_by', 36)->nullable();
            $t->timestamps();
            $t->index(['business_id', 'staff_id', 'status']);
        });

        Schema::create('payrolls', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->unsignedBigInteger('staff_id');
            $t->decimal('basic_salary', 22, 4)->default(0);
            $t->unsignedTinyInteger('salary_type')->default(1);
            $t->decimal('total_earning', 22, 4)->default(0);
            $t->decimal('total_allowance', 22, 4)->default(0);
            $t->decimal('bonus', 22, 4)->default(0);
            $t->decimal('total_deduction', 22, 4)->default(0);
            $t->decimal('gross_salary', 22, 4)->default(0);
            $t->decimal('taxable_salary', 22, 4)->nullable();
            $t->decimal('paid_days', 8, 2)->nullable();
            $t->decimal('period_days', 8, 2)->nullable();
            $t->decimal('tax', 22, 4)->default(0);
            $t->decimal('employer_tax', 22, 4)->default(0);
            $t->decimal('net_salary', 22, 4)->default(0);
            $t->decimal('total_cost', 22, 4)->default(0);
            $t->date('salary_month');
            $t->date('pay_date')->nullable();
            $t->string('payment_method', 20)->nullable();
            $t->unsignedBigInteger('payment_account_id')->nullable();
            $t->string('note')->nullable();
            $t->unsignedTinyInteger('status')->default(0);   // 0 unpaid, 1 paid
            $t->string('approved_by', 36)->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->string('created_by', 36)->nullable();
            $t->string('updated_by', 36)->nullable();
            $t->timestamps();
            $t->unique(['business_id', 'staff_id', 'salary_month']);
            $t->index(['business_id', 'salary_month', 'status']);
        });

        Schema::create('payroll_details', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->unsignedBigInteger('payroll_id');
            $t->unsignedBigInteger('staff_loan_id')->nullable();
            $t->string('title');
            $t->decimal('amount', 22, 4)->default(0);
            $t->boolean('is_taxable')->default(0);
            $t->unsignedTinyInteger('status')->default(1);   // 1 allowance, 0 deduction
            $t->timestamps();
            $t->index(['business_id', 'payroll_id']);
            $t->index('staff_loan_id');
        });

        Schema::create('payroll_tax_lines', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->unsignedBigInteger('payroll_id');
            $t->unsignedBigInteger('staff_id');
            $t->date('salary_month');
            $t->unsignedBigInteger('tax_group_id')->nullable();
            $t->unsignedBigInteger('tax_setting_id')->nullable();
            $t->string('label');
            $t->decimal('employee_amount', 22, 2)->default(0);
            $t->decimal('employer_amount', 22, 2)->default(0);
            $t->unsignedBigInteger('liability_account_id')->nullable();
            $t->timestamps();
            $t->index(['business_id', 'payroll_id']);
            $t->index(['business_id', 'liability_account_id', 'salary_month'], 'payroll_tax_lines_authority');
        });

        Schema::create('tax_remittances', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->unsignedBigInteger('liability_account_id');
            $t->date('salary_month');
            $t->decimal('amount', 22, 2);
            $t->date('payment_date');
            $t->unsignedBigInteger('payment_account_id')->nullable(); // E-Ride: paid from a payment account
            $t->unsignedBigInteger('source_account_id')->nullable();  // its ledger account
            $t->string('reference', 191)->nullable();                // the receipt number
            $t->text('note')->nullable();
            $t->timestamp('voided_at')->nullable();
            $t->text('void_reason')->nullable();
            $t->string('voided_by', 36)->nullable();
            $t->string('created_by', 36)->nullable();
            $t->string('updated_by', 36)->nullable();
            $t->timestamps();
            $t->index(['business_id', 'liability_account_id', 'salary_month'], 'tax_remittances_authority');
        });

        // paxhitestbed's print_settings, for its one slug that is ported: the pay slip
        Schema::create('print_settings', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->string('slug', 50);
            $t->string('title')->nullable();
            $t->text('header_left')->nullable();
            $t->text('header_center')->nullable();
            $t->text('header_right')->nullable();
            $t->longText('body')->nullable();
            $t->text('footer_left')->nullable();
            $t->text('footer_center')->nullable();
            $t->text('footer_right')->nullable();
            $t->string('logo_left')->nullable();
            $t->string('logo_right')->nullable();
            $t->string('background')->nullable();
            $t->string('width', 20)->nullable();
            $t->string('prefix', 20)->nullable();
            $t->boolean('status')->default(1);
            $t->timestamps();
            $t->unique(['business_id', 'slug']);
        });
    }

    public function down()
    {
        foreach (['print_settings', 'tax_remittances', 'payroll_tax_lines', 'payroll_details', 'payrolls', 'staff_loans', 'payroll_settings',
            'staff_tax_exemptions', 'tax_settings', 'tax_groups', 'ledger_staff', 'deduction_types', 'allowance_types', 'departments', 'designations'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
