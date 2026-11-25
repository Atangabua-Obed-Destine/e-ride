<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * paxhitestbed's hourly pay: staff paid by the hour (ledger_staff.salary_type 2, basic_salary is the
 * rate per hour) and their hourly attendance (StaffHourlyAttendance), which the payroll reads.
 *
 * paxhitestbed ties each session to a class of its timetable (subject, program, semester, section);
 * a bus branch has no timetable, so a session here is a stretch of work with its start and end time
 * and what it was ("task": a trip, a shift at the counter, loading). Codes as paxhitestbed's:
 * attendance 1 present, 2 absent, 3 leave, 4 holiday. Only present sessions with both times count.
 */
return new class extends Migration
{
    public function up()
    {
        Schema::create('staff_hourly_attendances', function (Blueprint $t) {
            $t->id();
            $t->unsignedInteger('business_id');
            $t->unsignedBigInteger('staff_id');
            $t->date('date');
            $t->time('start_time')->nullable();
            $t->time('end_time')->nullable();
            $t->string('task', 191)->nullable();
            $t->unsignedTinyInteger('attendance')->default(1);
            $t->string('note', 500)->nullable();
            $t->string('created_by', 36)->nullable();
            $t->string('updated_by', 36)->nullable();
            $t->timestamps();
            $t->index(['business_id', 'staff_id', 'date']);
        });

        Schema::table('payrolls', function (Blueprint $t) {
            $t->decimal('hours_worked', 8, 2)->nullable()->after('taxable_salary');
        });
    }

    public function down()
    {
        Schema::table('payrolls', function (Blueprint $t) {
            $t->dropColumn('hours_worked');
        });
        Schema::dropIfExists('staff_hourly_attendances');
    }
};
