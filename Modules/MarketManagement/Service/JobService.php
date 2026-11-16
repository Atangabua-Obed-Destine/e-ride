<?php

namespace Modules\MarketManagement\Service;

use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\MarketManagement\Entities\JobAlert;
use Modules\MarketManagement\Entities\JobApplication;
use Modules\MarketManagement\Entities\JobEmployer;
use Modules\MarketManagement\Entities\JobPost;
use Modules\MarketManagement\Entities\JobReport;
use Modules\MarketManagement\Entities\JobSave;
use Modules\MarketManagement\Lib\JobSettings;
use Modules\MarketManagement\Lib\MarketLinks;
use Modules\MarketManagement\Lib\MarketPush;
use Modules\MarketManagement\Lib\MarketSettings;
use Modules\UserManagement\Entities\User;
use Throwable;

/**
 * E-Ride Jobs: employers (checked once), jobs (checked each time, then live and alerted),
 * the feed, saves and reports, the admin's decisions, and the minute tick.
 */
class JobService
{
    // --------------------------------------------------------- employers

    public function employerOf(User $user): ?JobEmployer
    {
        return JobEmployer::where('user_id', $user->id)->first();
    }

    /**
     * Create or update the employer profile. The document is required the first time (and after a refusal);
     * changing the name, the kind or the document of a verified employer sends it back to E-Ride for a check.
     */
    public function saveEmployer(User $user, array $data, ?UploadedFile $logo, ?UploadedFile $doc): JobEmployer
    {
        $employer = $this->employerOf($user);
        if ($employer?->status === JobEmployer::FROZEN) {
            $this->fail('employer', translate('This employer account is closed. Contact E-Ride support.'));
        }
        $fields = array_intersect_key($data, array_flip(['kind', 'name', 'about', 'area_label', 'lat', 'lng', 'address', 'doc_type']));
        $fields['name'] = trim(preg_replace('/\s+/', ' ', (string)($fields['name'] ?? $employer?->name ?? '')));
        if ($fields['name'] === '') {
            $this->fail('name', translate('Give the name applicants will see.'));
        }
        $needsDoc = !$employer || !$employer->doc_file || $employer->status === JobEmployer::REJECTED;
        if ($needsDoc && !$doc) {
            $this->fail('doc', translate('Add a photo of your document: business licence, taxpayer card (NIU), or your ID card.'));
        }
        if ($doc && !in_array($fields['doc_type'] ?? $employer?->doc_type, JobEmployer::DOC_TYPES, true)) {
            $this->fail('doc_type', translate('Choose which document it is.'));
        }
        return DB::transaction(function () use ($user, $employer, $fields, $logo, $doc) {
            $employer = $employer ?: new JobEmployer(['user_id' => $user->id, 'status' => JobEmployer::PENDING]);
            $recheck = $employer->exists && $employer->status !== JobEmployer::PENDING && (
                    ($fields['name'] ?? $employer->name) !== $employer->name
                    || (isset($fields['kind']) && $fields['kind'] !== $employer->kind)
                    || $doc !== null);
            $employer->fill($fields);
            if ($logo) {
                $employer->logo = fileUploader(JobEmployer::LOGO_DIR, APPLICATION_IMAGE_FORMAT, $logo, $employer->logo);
            }
            if ($doc) {
                $old = $employer->doc_file;
                $employer->doc_file = $this->storePrivate(JobEmployer::DOC_DIR, $doc);
                if ($old) {
                    Storage::disk('local')->delete(JobEmployer::DOC_DIR . $old);
                }
            }
            if ($recheck) {
                $employer->status = JobEmployer::PENDING;
                $employer->review_note = null;
            }
            $employer->save();
            return $employer->fresh();
        });
    }

    /** Admin: the employer is who they say (document seen, place known, call made). Jobs waiting can now be approved. */
    public function verifyEmployer(JobEmployer $employer, ?string $adminId): JobEmployer
    {
        $employer->update(['status' => JobEmployer::VERIFIED, 'verified_at' => now(), 'reviewed_by' => $adminId, 'review_note' => null]);
        MarketPush::send($employer->user, 'job_employer_verified', 'You are a verified employer', 'Your jobs can now go live after a quick check.');
        return $employer;
    }

    /** Admin: not enough to verify (a blurry document, a wrong name). The employer can send it again. */
    public function refuseEmployer(JobEmployer $employer, string $note, ?string $adminId): JobEmployer
    {
        $employer->update(['status' => JobEmployer::REJECTED, 'review_note' => $note, 'reviewed_by' => $adminId]);
        MarketPush::send($employer->user, 'job_employer_refused', 'Your employer check needs a change', '{note}', ['note' => $note]);
        return $employer;
    }

    /** Admin: a scam. No more jobs; live jobs come down and their applicants are told kindly. */
    public function freezeEmployer(JobEmployer $employer, string $note, ?string $adminId): void
    {
        $employer->update(['status' => JobEmployer::FROZEN, 'review_note' => $note, 'reviewed_by' => $adminId]);
        foreach ($employer->jobs()->whereIn('status', [JobPost::REVIEW, JobPost::LIVE, JobPost::SENT_BACK, JobPost::HIDDEN])->get() as $job) {
            $this->close($job, 'removed', false);
        }
    }

    // --------------------------------------------------------- jobs (employer)

    public function create(JobEmployer $employer, array $data): JobPost
    {
        if (in_array($employer->status, [JobEmployer::FROZEN, JobEmployer::REJECTED], true)) {
            $this->fail('employer', $employer->status === JobEmployer::FROZEN
                ? translate('This employer account is closed. Contact E-Ride support.')
                : translate('Fix your employer check first, then post the job.'));
        }
        $open = $employer->jobs()->whereIn('status', [JobPost::REVIEW, JobPost::LIVE, JobPost::SENT_BACK])->count();
        if ($open >= JobSettings::all()['max_open_jobs']) {
            $this->fail('employer', translate('You have :n open jobs. Close one before posting another.', ['n' => $open]));
        }
        $fields = $this->jobFields($data);
        return DB::transaction(function () use ($employer, $fields) {
            $job = JobPost::create($fields + [
                    'code' => $this->newCode(), 'employer_id' => $employer->id, 'status' => JobPost::REVIEW, 'submitted_at' => now(),
                ]);
            $job->update(['flags' => $this->flags($job, $employer)]);
            $employer->increment('jobs_posted');
            return $job->fresh('employer');
        });
    }

    /** Fix a job sent back, or change one in review or live. A live job goes back to E-Ride for a check. */
    public function update(JobPost $job, array $data): JobPost
    {
        if (!in_array($job->status, [JobPost::REVIEW, JobPost::SENT_BACK, JobPost::LIVE], true)) {
            $this->fail('job', translate('This job is closed. Post a new one.'));
        }
        $job->fill($this->jobFields($data, $job));
        $job->status = JobPost::REVIEW;
        $job->review_note = null;
        $job->submitted_at = now();
        $job->flags = $this->flags($job, $job->employer);
        $job->save();
        return $job->fresh('employer');
    }

    /**
     * Close a job: by the employer, when its time is up, or when an admin removes it.
     * Waiting applicants get a kind note; those who already got a next step keep it.
     */
    public function close(JobPost $job, string $reason = 'employer', bool $tellEmployer = false): JobPost
    {
        if (in_array($job->status, [JobPost::CLOSED, JobPost::REJECTED], true)) {
            return $job;
        }
        $job->update(['status' => $reason === 'removed' ? JobPost::REJECTED : JobPost::CLOSED, 'closed_at' => now(), 'closed_reason' => $reason]);
        $waiting = JobApplication::with('user')->where('job_id', $job->id)->whereIn('status', JobApplication::WAITING)->get();
        foreach ($waiting as $application) {
            $application->update(['status' => JobApplication::CLOSED, 'closed_reason' => 'job_closed', 'decided_at' => now()]);
            MarketPush::send($application->user, 'job_application_closed', $reason === 'filled' ? '{job} is filled' : '{job} is closed',
                $reason === 'filled' ? 'Thank you for applying. The job is filled. Keep going: new jobs come every day.' : 'Thank you for applying. Keep going: new jobs come every day.',
                ['job' => $job->title], ['job_code' => $job->code, 'application_id' => $application->id]);
        }
        if ($tellEmployer) {
            MarketPush::send($job->employer?->user, 'job_closed', '{job} is closed', 'It was open :days days. Post it again any time.',
                ['job' => $job->title, 'days' => JobSettings::all()['job_days']], ['job_code' => $job->code]);
        }
        return $job;
    }

    // --------------------------------------------------------- jobs (E-Ride review)

    public function approve(JobPost $job, ?string $adminId): JobPost
    {
        // Read it again: the employer may have been verified since this job was loaded.
        $employer = $job->employer()->first();
        $job->setRelation('employer', $employer);
        if (!$employer?->isVerified()) {
            $this->fail('job', translate('Verify the employer first.'));
        }
        if (!in_array($job->status, [JobPost::REVIEW, JobPost::HIDDEN], true)) {
            $this->fail('job', translate('This job is not waiting for a check.'));
        }
        $first = $job->live_at === null;
        $job->update([
            'status' => JobPost::LIVE, 'reviewed_by' => $adminId, 'reviewed_at' => now(), 'review_note' => null,
            'live_at' => $job->live_at ?? now(),
            'closes_at' => $job->closes_at && $job->closes_at->isFuture() ? $job->closes_at : now()->addDays(JobSettings::all()['job_days']),
        ]);
        MarketPush::send($employer->user, 'job_live', '{job} is live', 'Share it on WhatsApp. You get a notification for each application.',
            ['job' => $job->title], ['job_code' => $job->code]);
        if ($first) {
            $this->sendAlerts($job->fresh('employer'));
        }
        return $job;
    }

    /** Something to fix (pay missing, wording). The employer sees the reason and sends it again. */
    public function sendBack(JobPost $job, string $reason, ?string $adminId): JobPost
    {
        $job->update(['status' => JobPost::SENT_BACK, 'review_note' => $reason, 'reviewed_by' => $adminId, 'reviewed_at' => now()]);
        MarketPush::send($job->employer?->user, 'job_sent_back', '{job} needs a change', '{reason}', ['job' => $job->title, 'reason' => $reason],
            ['job_code' => $job->code]);
        return $job;
    }

    /** A scam or not a job. With $freeze, the employer is closed too. */
    public function reject(JobPost $job, string $reason, bool $freeze, ?string $adminId): JobPost
    {
        $job->update(['review_note' => $reason, 'reviewed_by' => $adminId, 'reviewed_at' => now()]);
        $this->close($job, 'removed', false);
        if ($freeze && $job->employer) {
            $this->freezeEmployer($job->employer, $reason, $adminId);
        } else {
            MarketPush::send($job->employer?->user, 'job_rejected', '{job} was not accepted', '{reason}', ['job' => $job->title, 'reason' => $reason],
                ['job_code' => $job->code]);
        }
        return $job->fresh();
    }

    /** What the reviewer should look at: fee words, low pay, an employer not verified yet, banned words. */
    public function flags(JobPost $job, ?JobEmployer $employer): array
    {
        $text = $job->title . ' ' . $job->description . ' ' . $job->requirements . ' ' . implode(' ', $job->questionList());
        $flags = [];
        if ($fee = JobSettings::firstIn($text, JobSettings::feeWords())) {
            $flags[] = 'fee_words:' . $fee;
        }
        if ($banned = JobSettings::firstIn($text, MarketSettings::bannedList())) {
            $flags[] = 'banned:' . $banned;
        }
        $monthly = $this->monthly($job->pay_max ?: $job->pay_min, $job->pay_period);
        $min = JobSettings::all()['min_month_pay'];
        if ($monthly !== null && $job->type === 'full_time' && $min > 0 && $monthly < $min) {
            $flags[] = 'pay_low';
        }
        if ($monthly !== null && $monthly > 2000000) {
            $flags[] = 'pay_high';
        }
        if (!$employer?->isVerified()) {
            $flags[] = 'employer_not_verified';
        }
        return $flags;
    }

    // --------------------------------------------------------- finding jobs

    public function feed(User $viewer, array $filters, int $page = 1): LengthAwarePaginator
    {
        $query = JobPost::with('employer')
            ->where('status', JobPost::LIVE)
            ->where(fn($q) => $q->whereNull('closes_at')->orWhere('closes_at', '>', now()))
            ->whereHas('employer', fn($q) => $q->where('status', '!=', JobEmployer::FROZEN));
        if (!empty($filters['category']) && in_array($filters['category'], JobPost::CATEGORIES, true)) {
            $query->where('category', $filters['category']);
        }
        if (!empty($filters['type']) && in_array($filters['type'], JobPost::TYPES, true)) {
            $query->where('type', $filters['type']);
        }
        if (!empty($filters['saved'])) {
            $query->whereIn('id', JobSave::where('user_id', $viewer->id)->select('job_id'));
        }
        $q = trim((string)($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
            $query->where(fn($w) => $w->where('title', 'like', $like)->orWhere('description', 'like', $like)->orWhere('area_label', 'like', $like)
                ->orWhereHas('employer', fn($e) => $e->where('name', 'like', $like)));
        }
        return $query->orderByDesc('live_at')->paginate(20, ['*'], 'page', max(1, $page));
    }

    /** A job by its code, as the viewer may see it (null: not there). The employer sees their own in any state. */
    public function find(string $code, ?User $viewer): ?JobPost
    {
        $job = JobPost::with('employer')->where('code', $code)->first();
        if (!$job) {
            return null;
        }
        $mine = $viewer && $job->employer?->user_id === $viewer->id;
        if (!$mine && !$job->isPublic()) {
            return null;
        }
        return $job;
    }

    public function countView(JobPost $job, ?User $viewer): void
    {
        if ($job->isLive() && $job->employer?->user_id !== $viewer?->id) {
            JobPost::whereKey($job->id)->increment('views');
        }
    }

    public function toggleSave(User $user, JobPost $job): bool
    {
        $existing = JobSave::where('user_id', $user->id)->where('job_id', $job->id)->first();
        if ($existing) {
            $existing->delete();
            return false;
        }
        JobSave::create(['user_id' => $user->id, 'job_id' => $job->id]);
        return true;
    }

    public function report(User $user, JobPost $job, string $reason, ?string $note): void
    {
        if ($job->employer?->user_id === $user->id) {
            $this->fail('job', translate('This is your own job.'));
        }
        if (JobReport::where('job_id', $job->id)->where('reporter_id', $user->id)->exists()) {
            $this->fail('job', translate('You already reported this job. E-Ride is looking at it.'));
        }
        JobReport::create(['job_id' => $job->id, 'reporter_id' => $user->id, 'reason' => $reason, 'note' => $note, 'status' => 'open']);
        $job->increment('report_count');
        $distinct = JobReport::where('job_id', $job->id)->where('status', 'open')->count();
        // One "they ask for money" report on an employer not verified is enough to take it down.
        $unverifiedFee = $reason === 'fee' && !$job->employer?->isVerified();
        if ($job->status === JobPost::LIVE && ($distinct >= JobSettings::all()['hide_after_reports'] || $unverifiedFee)) {
            $job->update(['status' => JobPost::HIDDEN, 'review_note' => 'Reported']);
        }
    }

    // --------------------------------------------------------- alerts

    public function alertOf(User $user): ?JobAlert
    {
        return JobAlert::where('user_id', $user->id)->first();
    }

    public function saveAlert(User $user, array $categories, array $types, bool $enabled): JobAlert
    {
        return JobAlert::updateOrCreate(['user_id' => $user->id], [
            'categories' => array_values(array_intersect(JobPost::CATEGORIES, $categories)),
            'types' => array_values(array_intersect(JobPost::TYPES, $types)),
            'enabled' => $enabled,
        ]);
    }

    /** A job just went live: tell the people whose alert fits (a few a day at most each). Returns how many. */
    public function sendAlerts(JobPost $job): int
    {
        $limit = JobSettings::all()['alerts_per_day'];
        $today = now()->toDateString();
        $sent = 0;
        $pay = $this->payText($job);
        JobAlert::with('user')->where('enabled', true)->where('user_id', '!=', $job->employer?->user_id)
            ->chunkById(500, function ($alerts) use ($job, $limit, $today, $pay, &$sent) {
                foreach ($alerts as $alert) {
                    if (!$alert->matches($job)) {
                        continue;
                    }
                    $count = $alert->sent_on?->toDateString() === $today ? $alert->sent_count : 0;
                    if ($count >= $limit) {
                        continue;
                    }
                    $alert->update(['sent_on' => $today, 'sent_count' => $count + 1]);
                    MarketPush::send($alert->user, 'job_alert', 'New job for you: {job}', '{employer} · {pay}',
                        ['job' => $job->title, 'employer' => $job->employer?->name, 'pay' => $pay], ['job_code' => $job->code]);
                    $sent++;
                }
            });
        $job->update(['alerted' => $sent]);
        return $sent;
    }

    // --------------------------------------------------------- tick

    /** Every minute: jobs past their date close; applications nobody answered close kindly. */
    public function tick(): array
    {
        $counts = ['jobs_closed' => 0, 'applications_closed' => 0];
        foreach (JobPost::with('employer.user')->where('status', JobPost::LIVE)->whereNotNull('closes_at')->where('closes_at', '<=', now())->limit(200)->get() as $job) {
            try {
                $this->close($job, 'ended', true);
                $counts['jobs_closed']++;
            } catch (Throwable $e) {
                Log::error('Job close failed', ['job' => $job->id, 'error' => $e->getMessage()]);
            }
        }
        $stale = now()->subDays(JobSettings::all()['stale_days']);
        $old = JobApplication::with(['user', 'job'])->whereIn('status', JobApplication::WAITING)->where('created_at', '<=', $stale)->limit(200)->get();
        foreach ($old as $application) {
            $application->update(['status' => JobApplication::CLOSED, 'closed_reason' => 'no_answer', 'decided_at' => now()]);
            MarketPush::send($application->user, 'job_application_closed', 'No answer for {job}', 'The employer did not answer in time, so we closed it for you. Keep applying.',
                ['job' => $application->job?->title], ['job_code' => $application->job?->code, 'application_id' => $application->id]);
            $counts['applications_closed']++;
        }
        return $counts;
    }

    // --------------------------------------------------------- helpers

    public function link(JobPost $job): string
    {
        return MarketLinks::base() . '/j/' . $job->code;
    }

    /** "60,000 to 75,000 FCFA / month" in the current language. */
    public function payText(JobPost $job): string
    {
        $min = getCurrencyFormat($job->pay_min);
        $amount = $job->pay_max && $job->pay_max > $job->pay_min ? translate(':min to :max', ['min' => $min, 'max' => getCurrencyFormat($job->pay_max)]) : $min;
        return $amount . ' / ' . translate('per_' . $job->pay_period);
    }

    /** About how much in a month, to compare pay (null for "per job"). */
    public function monthly(?float $amount, string $period): ?float
    {
        if (!$amount) {
            return null;
        }
        return match ($period) {
            'month' => $amount,
            'week' => $amount * 4.33,
            'day' => $amount * 26,
            'hour' => $amount * 8 * 26,
            default => null,
        };
    }

    private function jobFields(array $data, ?JobPost $job = null): array
    {
        $f = array_intersect_key($data, array_flip(['category', 'type', 'title', 'description', 'requirements', 'pay_min', 'pay_max', 'pay_period',
            'schedule', 'places', 'area_label', 'lat', 'lng', 'address']));
        foreach (['title', 'description', 'requirements', 'schedule', 'area_label', 'address'] as $k) {
            if (array_key_exists($k, $f)) {
                $f[$k] = $f[$k] === null ? null : trim(preg_replace('/[ \t]+/', ' ', (string)$f[$k]));
            }
        }
        $min = (float)($f['pay_min'] ?? $job?->pay_min ?? 0);
        $max = isset($f['pay_max']) && $f['pay_max'] !== '' && $f['pay_max'] !== null ? (float)$f['pay_max'] : null;
        if ($min <= 0) {
            $this->fail('pay_min', translate('Give the pay. Jobs without a clear pay are not accepted.'));
        }
        if ($max !== null && $max < $min) {
            $this->fail('pay_max', translate('The highest pay is lower than the lowest.'));
        }
        if (array_key_exists('pay_max', $f) || $job === null) {
            $f['pay_max'] = $max && $max > $min ? $max : null;
        }
        if (array_key_exists('skills', $data)) {
            $skills = is_array($data['skills']) ? $data['skills'] : (json_decode((string)$data['skills'], true) ?: array_filter(explode(',', (string)$data['skills'])));
            $f['skills'] = array_values(array_unique(array_filter(array_map(fn($s) => trim(mb_substr((string)$s, 0, 40)), array_slice($skills, 0, JobPost::MAX_SKILLS)))));
        }
        if (array_key_exists('required_links', $data)) {
            $req = is_array($data['required_links']) ? $data['required_links'] : (json_decode((string)$data['required_links'], true) ?: array_filter(explode(',', (string)$data['required_links'])));
            $f['required_links'] = array_values(array_intersect(\Modules\MarketManagement\Entities\JobWorkCard::LINK_TYPES, $req));
        }
        if (array_key_exists('questions', $data)) {
            $questions = is_array($data['questions']) ? $data['questions'] : (json_decode((string)$data['questions'], true) ?: []);
            $f['questions'] = array_values(array_filter(array_map(fn($q) => trim(mb_substr((string)$q, 0, 120)), array_slice($questions, 0, JobPost::MAX_QUESTIONS))));
        }
        return $f;
    }

    private function storePrivate(string $dir, UploadedFile $file): string
    {
        $name = date('Y-m-d') . '-' . bin2hex(random_bytes(8)) . '.' . strtolower($file->getClientOriginalExtension() ?: 'jpg');
        Storage::disk('local')->putFileAs(rtrim($dir, '/'), $file, $name);
        return $name;
    }

    /** 6 letters and digits people can read out loud. */
    private function newCode(): string
    {
        do {
            $code = '';
            $alphabet = 'abcdefghjkmnpqrstuvwxyz23456789';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (JobPost::where('code', $code)->exists());
        return $code;
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
