<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E-Ride: the department an expense is spent for (optional), so departmental budgets can
 * measure it. posinnova's budgets measure a department (a business location) on the location
 * of the POS document; the bus books' departments are the payroll's (departments table), and an
 * expense says which one here. Payroll takes its department from the staff member.
 */
return new class extends Migration
{
    public function up()
    {
        if (Schema::hasTable('expenses') && ! Schema::hasColumn('expenses', 'department_id')) {
            Schema::table('expenses', function (Blueprint $t) {
                $t->unsignedBigInteger('department_id')->nullable()->after('category_id');
                $t->index(['business_id', 'department_id']);
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('expenses', 'department_id')) {
            Schema::table('expenses', function (Blueprint $t) {
                $t->dropIndex(['business_id', 'department_id']);
                $t->dropColumn('department_id');
            });
        }
    }
};
