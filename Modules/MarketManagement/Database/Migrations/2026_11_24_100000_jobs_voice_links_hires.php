<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E-Ride Jobs, second step: a 30-second voice intro and professional links on the Work Card,
 * links an employer can require, a map pin on the next step ("Ride there"), and light hire
 * tracking (the employer marks hired, the person confirms; the employer shows "N hired").
 * New timestamp columns are nullable (MariaDB, see the first Jobs migration).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('job_work_cards', function (Blueprint $table) {
            $table->string('voice_file')->nullable()->after('cv_name');
            $table->unsignedTinyInteger('voice_seconds')->nullable()->after('voice_file');
            // [{"type":"linkedin","url":"https://www.linkedin.com/in/..."}]
            $table->json('links')->nullable()->after('voice_seconds');
        });

        Schema::table('job_posts', function (Blueprint $table) {
            // ["linkedin","github"]: links an applicant must have on their Work Card
            $table->json('required_links')->nullable()->after('questions');
            $table->unsignedSmallInteger('hired_count')->default(0)->after('applications_count');
        });

        Schema::table('job_applications', function (Blueprint $table) {
            $table->decimal('next_step_lat', 10, 7)->nullable()->after('next_step_place');
            $table->decimal('next_step_lng', 10, 7)->nullable()->after('next_step_lat');
            $table->timestamp('hired_at')->nullable()->after('decided_at');
            $table->timestamp('hire_confirmed_at')->nullable()->after('hired_at');
            $table->timestamp('hire_declined_at')->nullable()->after('hire_confirmed_at');
        });

        Schema::table('job_employers', function (Blueprint $table) {
            // People who confirmed they were hired through E-Ride.
            $table->unsignedInteger('hires_count')->default(0)->after('jobs_posted');
        });
    }

    public function down(): void
    {
        Schema::table('job_employers', fn(Blueprint $t) => $t->dropColumn('hires_count'));
        Schema::table('job_applications', fn(Blueprint $t) => $t->dropColumn(['next_step_lat', 'next_step_lng', 'hired_at', 'hire_confirmed_at', 'hire_declined_at']));
        Schema::table('job_posts', fn(Blueprint $t) => $t->dropColumn(['required_links', 'hired_count']));
        Schema::table('job_work_cards', fn(Blueprint $t) => $t->dropColumn(['voice_file', 'voice_seconds', 'links']));
    }
};
