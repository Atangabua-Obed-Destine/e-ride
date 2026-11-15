<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * E-Ride Jobs: verified employers post jobs that E-Ride checks before they go live; job seekers
 * make a Work Card once and apply in one tap; employers shortlist and send the next step.
 * E-Ride does not run interviews or track hires. Applying never costs anything.
 *
 * Every new timestamp column is nullable: on MariaDB the first NOT NULL timestamp of a table
 * gets ON UPDATE CURRENT_TIMESTAMP and would change by itself on every update.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('job_employers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique();
            // business | private (hiring for a home: nanny, house help)
            $table->string('kind', 10)->default('business');
            $table->string('name', 120);
            $table->string('about', 600)->nullable();
            $table->string('logo')->nullable();
            $table->string('area_label', 80)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('address', 191)->nullable();
            // licence | niu | id
            $table->string('doc_type', 12)->nullable();
            $table->string('doc_file')->nullable();
            // pending | verified | rejected | frozen
            $table->string('status', 12)->default('pending');
            $table->string('review_note', 300)->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->unsignedInteger('jobs_posted')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index('status');
        });

        Schema::create('job_posts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 10)->unique();
            $table->uuid('employer_id')->index();
            $table->string('category', 20);
            // full_time | part_time | day_job | internship
            $table->string('type', 12);
            $table->string('title', 120);
            $table->text('description');
            $table->string('requirements', 600)->nullable();
            $table->json('skills')->nullable();
            $table->decimal('pay_min', 14, 2);
            $table->decimal('pay_max', 14, 2)->nullable();
            // month | week | day | hour | job
            $table->string('pay_period', 8)->default('month');
            // "Mon to Sat, 07:00 to 16:00"
            $table->string('schedule', 120)->nullable();
            $table->unsignedSmallInteger('places')->default(1);
            $table->string('area_label', 80)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('address', 191)->nullable();
            // ["Can you start before 1 December?", ...] yes or no, up to 3
            $table->json('questions')->nullable();
            // review | live | sent_back | rejected | closed | hidden
            $table->string('status', 12)->default('review');
            $table->string('review_note', 300)->nullable();
            // ["fee_words:frais de dossier", "pay_low", "employer_not_verified"]
            $table->json('flags')->nullable();
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('live_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('closed_reason', 20)->nullable();
            $table->unsignedInteger('applications_count')->default(0);
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('alerted')->default(0);
            $table->unsignedSmallInteger('report_count')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->index(['status', 'live_at']);
        });

        Schema::create('job_work_cards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique();
            $table->string('name', 80);
            $table->string('area_label', 80)->nullable();
            $table->string('languages', 80)->nullable();
            $table->string('headline', 120)->nullable();
            $table->json('skills')->nullable();
            // [{"title":"Cashier","place":"Ndop Supermarket","from":"2023","to":"2025"}]
            $table->json('experience')->nullable();
            $table->string('education', 300)->nullable();
            // ["full_time","day_job"]
            $table->json('looking_for')->nullable();
            $table->string('cv_file')->nullable();
            $table->string('cv_name', 120)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('job_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('job_id')->index();
            $table->uuid('user_id')->index();
            // [true,false,true] in the order of the job's questions
            $table->json('answers')->nullable();
            $table->string('message', 600)->nullable();
            $table->boolean('share_phone')->default(false);
            // sent | seen | shortlisted | next_step | not_selected | withdrawn | closed
            $table->string('status', 14)->default('sent');
            $table->string('employer_note', 400)->nullable();
            $table->text('next_step')->nullable();
            $table->timestamp('next_step_at')->nullable();
            $table->string('next_step_place', 191)->nullable();
            $table->timestamp('next_step_sent_at')->nullable();
            $table->timestamp('next_step_read_at')->nullable();
            $table->timestamp('seen_at')->nullable();
            $table->timestamp('shortlisted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            // job_closed | no_answer
            $table->string('closed_reason', 20)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['job_id', 'user_id']);
            $table->index(['job_id', 'status']);
        });

        Schema::create('job_alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique();
            $table->json('categories')->nullable();
            $table->json('types')->nullable();
            $table->boolean('enabled')->default(true);
            $table->date('sent_on')->nullable();
            $table->unsignedTinyInteger('sent_count')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });

        Schema::create('job_saves', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id');
            $table->uuid('job_id');
            $table->timestamp('created_at')->nullable();
            $table->unique(['user_id', 'job_id']);
        });

        Schema::create('job_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('job_id')->index();
            $table->uuid('reporter_id');
            // fee | fake | pay | rude | other
            $table->string('reason', 12);
            $table->string('note', 300)->nullable();
            // open | kept | removed
            $table->string('status', 10)->default('open');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unique(['job_id', 'reporter_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_reports');
        Schema::dropIfExists('job_saves');
        Schema::dropIfExists('job_alerts');
        Schema::dropIfExists('job_applications');
        Schema::dropIfExists('job_work_cards');
        Schema::dropIfExists('job_posts');
        Schema::dropIfExists('job_employers');
    }
};
