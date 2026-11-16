<?php

namespace Modules\MarketManagement\Service;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Modules\MarketManagement\Entities\JobApplication;
use Modules\MarketManagement\Entities\JobEmployer;
use Modules\MarketManagement\Entities\JobPost;
use Modules\MarketManagement\Entities\JobWorkCard;
use Modules\MarketManagement\Lib\MarketPush;
use Modules\MarketManagement\Lib\MarketSettings;
use Modules\UserManagement\Entities\User;

/**
 * The job seeker's side (Work Card, apply, withdraw, my applications) and the employer's
 * answers (seen, shortlist, not this time, the next step). No interviews or hiring in E-Ride.
 */
class JobApplicationService
{
    // --------------------------------------------------------- Work Card

    public function cardOf(User $user): ?JobWorkCard
    {
        return JobWorkCard::where('user_id', $user->id)->first();
    }

    public function saveCard(User $user, array $data, ?UploadedFile $cv, bool $removeCv, ?UploadedFile $voice = null, bool $removeVoice = false): JobWorkCard
    {
        $card = $this->cardOf($user) ?: new JobWorkCard(['user_id' => $user->id]);
        $f = array_intersect_key($data, array_flip(['name', 'area_label', 'languages', 'headline', 'education']));
        foreach ($f as $k => $v) {
            $f[$k] = $v === null ? null : trim(preg_replace('/\s+/', ' ', (string)$v));
        }
        if (array_key_exists('skills', $data)) {
            $skills = $this->list($data['skills']);
            $f['skills'] = array_values(array_unique(array_filter(array_map(fn($s) => trim(mb_substr((string)$s, 0, 40)), array_slice($skills, 0, JobWorkCard::MAX_SKILLS)))));
        }
        if (array_key_exists('experience', $data)) {
            $out = [];
            foreach (array_slice($this->list($data['experience']), 0, JobWorkCard::MAX_EXPERIENCE) as $e) {
                $title = trim(mb_substr((string)($e['title'] ?? ''), 0, 60));
                if ($title !== '') {
                    $out[] = ['title' => $title, 'place' => trim(mb_substr((string)($e['place'] ?? ''), 0, 60)),
                        'from' => trim(mb_substr((string)($e['from'] ?? ''), 0, 12)), 'to' => trim(mb_substr((string)($e['to'] ?? ''), 0, 12))];
                }
            }
            $f['experience'] = $out;
        }
        if (array_key_exists('links', $data)) {
            $f['links'] = $this->cleanLinks($this->list($data['links']));
        }
        if (array_key_exists('looking_for', $data)) {
            $f['looking_for'] = array_values(array_intersect(JobPost::TYPES, $this->list($data['looking_for'])));
        }
        $name = $f['name'] ?? $card->name ?? trim($user->first_name . ' ' . $user->last_name);
        if (trim((string)$name) === '') {
            $this->fail('name', translate('Give your name.'));
        }
        $f['name'] = $name;
        $card->fill($f);
        if ($removeCv && $card->cv_file) {
            Storage::disk('local')->delete(JobWorkCard::CV_DIR . $card->cv_file);
            $card->cv_file = null;
            $card->cv_name = null;
        }
        if ($cv) {
            $old = $card->cv_file;
            $name = date('Y-m-d') . '-' . bin2hex(random_bytes(8)) . '.' . strtolower($cv->extension() ?: ($cv->getClientOriginalExtension() ?: 'pdf'));
            Storage::disk('local')->putFileAs(rtrim(JobWorkCard::CV_DIR, '/'), $cv, $name);
            $card->cv_file = $name;
            $card->cv_name = mb_substr($cv->getClientOriginalName() ?: 'CV', 0, 120);
            if ($old) {
                Storage::disk('local')->delete(JobWorkCard::CV_DIR . $old);
            }
        }
        if ($removeVoice && $card->voice_file) {
            Storage::disk('local')->delete(JobWorkCard::VOICE_DIR . $card->voice_file);
            $card->voice_file = null;
            $card->voice_seconds = null;
        }
        if ($voice) {
            $seconds = (int)($data['voice_seconds'] ?? 0);
            if ($seconds > JobWorkCard::MAX_VOICE_SECONDS) {
                $this->fail('voice', translate('Keep your voice intro to 30 seconds.'));
            }
            $old = $card->voice_file;
            $ext = strtolower($voice->getClientOriginalExtension() ?: ($voice->extension() ?: 'm4a'));
            $name = date('Y-m-d') . '-' . bin2hex(random_bytes(8)) . '.' . (in_array($ext, ['m4a', 'mp4', 'aac', 'mp3', 'ogg', 'oga', 'wav', 'webm', '3gp'], true) ? $ext : 'm4a');
            Storage::disk('local')->putFileAs(rtrim(JobWorkCard::VOICE_DIR, '/'), $voice, $name);
            $card->voice_file = $name;
            $card->voice_seconds = max(1, min(JobWorkCard::MAX_VOICE_SECONDS, $seconds ?: 30));
            if ($old) {
                Storage::disk('local')->delete(JobWorkCard::VOICE_DIR . $old);
            }
        }
        $card->save();
        return $card->fresh();
    }

    /**
     * Professional links: a known type and a web address. LinkedIn and GitHub must point to their sites.
     * @return array<int, array{type: string, url: string}>
     */
    private function cleanLinks(array $links): array
    {
        $out = [];
        foreach (array_slice($links, 0, JobWorkCard::MAX_LINKS) as $l) {
            $type = (string)($l['type'] ?? '');
            $url = trim(mb_substr((string)($l['url'] ?? ''), 0, 300));
            if ($url === '') {
                continue;
            }
            if (!preg_match('#^https?://#i', $url)) {
                $url = 'https://' . $url;
            }
            $host = strtolower((string)parse_url($url, PHP_URL_HOST));
            if (!in_array($type, JobWorkCard::LINK_TYPES, true) || $host === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
                $this->fail('links', translate('This link does not look right: :url', ['url' => $url]));
            }
            if ($type === 'linkedin' && !str_ends_with($host, 'linkedin.com')) {
                $this->fail('links', translate('Your LinkedIn link must be a linkedin.com address.'));
            }
            if ($type === 'github' && !str_ends_with($host, 'github.com')) {
                $this->fail('links', translate('Your GitHub link must be a github.com address.'));
            }
            $out[] = ['type' => $type, 'url' => $url];
        }
        return $out;
    }

    // --------------------------------------------------------- applying

    /** @param bool[] $answers in the order of the job's questions */
    public function apply(User $user, JobPost $job, array $answers, ?string $message, bool $sharePhone): JobApplication
    {
        if (!$job->isLive()) {
            $this->fail('job', translate('This job is not open for applications.'));
        }
        if ($job->employer?->user_id === $user->id) {
            $this->fail('job', translate('This is your own job.'));
        }
        $card = $this->cardOf($user);
        if (!$card || !$card->canApply()) {
            $this->fail('card', translate('Finish your Work Card first: your name, and your skills or experience.'));
        }
        $missing = array_values(array_diff($job->requiredLinks(), $card->linkTypes()));
        if ($missing) {
            $this->fail('links', translate('This job asks for your :links link. Add it to your Work Card, then apply.',
                ['links' => implode(', ', array_map(fn($t) => translate('link_' . $t), $missing))]));
        }
        $existing = JobApplication::where('job_id', $job->id)->where('user_id', $user->id)->first();
        if ($existing && $existing->status !== JobApplication::WITHDRAWN) {
            $this->fail('job', translate('You already applied to this job.'));
        }
        $questions = $job->questionList();
        if (count($answers) < count($questions)) {
            $this->fail('answers', translate('Answer the employer\'s questions.'));
        }
        $answers = array_map(fn($a) => $a === true || $a === 1 || $a === '1' || $a === 'yes' || $a === 'true', array_slice(array_values($answers), 0, count($questions)));
        $message = $message !== null ? trim(mb_substr($message, 0, 600)) : null;
        if ($message && ($word = \Modules\MarketManagement\Lib\JobSettings::firstIn($message, MarketSettings::bannedList()))) {
            $this->fail('message', translate('Your message has a word that is not allowed: :word', ['word' => $word]));
        }
        $application = DB::transaction(function () use ($existing, $user, $job, $answers, $message, $sharePhone) {
            $fields = ['answers' => $answers, 'message' => $message ?: null, 'share_phone' => $sharePhone, 'status' => JobApplication::SENT,
                'employer_note' => null, 'next_step' => null, 'next_step_at' => null, 'next_step_place' => null, 'next_step_sent_at' => null,
                'next_step_read_at' => null, 'seen_at' => null, 'shortlisted_at' => null, 'decided_at' => null, 'closed_reason' => null];
            if ($existing) {
                $existing->update($fields);
                $application = $existing;
            } else {
                $application = JobApplication::create($fields + ['job_id' => $job->id, 'user_id' => $user->id]);
            }
            JobPost::whereKey($job->id)->increment('applications_count');
            return $application;
        });
        MarketPush::send($job->employer?->user, 'job_application_new', 'New application: {job}', '{name} applied. Open their Work Card.',
            ['job' => $job->title, 'name' => $this->cardOf($user)?->name], ['job_code' => $job->code, 'application_id' => $application->id]);
        return $application->fresh(['job.employer']);
    }

    public function withdraw(User $user, JobApplication $application): JobApplication
    {
        $this->asApplicant($user, $application);
        if (!in_array($application->status, JobApplication::ACTIVE, true)) {
            $this->fail('application', translate('This application is already closed.'));
        }
        $application->update(['status' => JobApplication::WITHDRAWN, 'decided_at' => now()]);
        JobPost::whereKey($application->job_id)->where('applications_count', '>', 0)->decrement('applications_count');
        return $application;
    }

    /** The applicant read the next step (the employer sees it was read). */
    public function markNextStepRead(User $user, JobApplication $application): void
    {
        if ($application->user_id === $user->id && $application->status === JobApplication::NEXT_STEP && !$application->next_step_read_at) {
            $application->update(['next_step_read_at' => now()]);
        }
    }

    public function mine(User $user): Collection
    {
        return JobApplication::with('job.employer')->where('user_id', $user->id)->latest('updated_at')->limit(100)->get();
    }

    // --------------------------------------------------------- employer side

    /**
     * Applicants of a job, best match first: yes answers, skills that fit, then who applied first.
     * $tab: new (sent, seen), shortlist (shortlisted, next_step), other (not selected, closed). Withdrawn are left out.
     */
    public function applicants(JobPost $job, string $tab): Collection
    {
        $statuses = match ($tab) {
            'shortlist' => [JobApplication::SHORTLISTED, JobApplication::NEXT_STEP, JobApplication::HIRED],
            'other' => [JobApplication::NOT_SELECTED, JobApplication::CLOSED],
            default => [JobApplication::SENT, JobApplication::SEEN],
        };
        $list = JobApplication::with('user')->where('job_id', $job->id)->whereIn('status', $statuses)->oldest()->get();
        $cards = JobWorkCard::whereIn('user_id', $list->pluck('user_id'))->get()->keyBy('user_id');
        foreach ($list as $a) {
            $a->setRelation('card', $cards[$a->user_id] ?? null);
            $a->fit = $this->fit($job, $a, $cards[$a->user_id] ?? null);
        }
        return $list->sortBy([fn($a, $b) => $b->fit['score'] <=> $a->fit['score'], fn($a, $b) => $a->created_at <=> $b->created_at])->values();
    }

    /**
     * How well an applicant fits: yes answers and the job's skills found on the card.
     * Only a way to sort: E-Ride never hides an applicant.
     */
    public function fit(JobPost $job, JobApplication $application, ?JobWorkCard $card): array
    {
        $questions = count($job->questionList());
        $yes = min($questions, $application->yesCount());
        $wanted = array_map(fn($s) => MarketSettings::fold($s), (array)($job->skills ?? []));
        $has = array_map(fn($s) => MarketSettings::fold($s), (array)($card?->skills ?? []));
        $skills = count(array_intersect($wanted, $has));
        $of = $questions + count($wanted);
        return ['yes' => $yes, 'questions' => $questions, 'skills' => $skills, 'skills_of' => count($wanted), 'score' => $yes + $skills, 'of' => $of];
    }

    /** The employer opens an application: it becomes "seen" for the applicant. */
    public function open(User $employerUser, JobApplication $application): JobApplication
    {
        $this->asEmployer($employerUser, $application);
        if ($application->status === JobApplication::SENT) {
            $application->update(['status' => JobApplication::SEEN, 'seen_at' => now()]);
        }
        return $application;
    }

    public function shortlist(User $employerUser, JobApplication $application): JobApplication
    {
        $this->asEmployer($employerUser, $application);
        $this->expect($application, [JobApplication::SENT, JobApplication::SEEN, JobApplication::NOT_SELECTED]);
        $application->update(['status' => JobApplication::SHORTLISTED, 'shortlisted_at' => now(), 'seen_at' => $application->seen_at ?? now()]);
        MarketPush::send($application->user, 'job_shortlisted', 'You are on the shortlist', '{employer} shortlisted you for {job}. Watch for their next step.',
            $this->words($application), $this->data($application));
        return $application;
    }

    public function notSelected(User $employerUser, JobApplication $application, ?string $note): JobApplication
    {
        $this->asEmployer($employerUser, $application);
        $this->expect($application, [JobApplication::SENT, JobApplication::SEEN, JobApplication::SHORTLISTED, JobApplication::NEXT_STEP]);
        $note = $note !== null ? trim(mb_substr($note, 0, 400)) : null;
        $application->update(['status' => JobApplication::NOT_SELECTED, 'employer_note' => $note ?: null, 'decided_at' => now(), 'seen_at' => $application->seen_at ?? now()]);
        MarketPush::send($application->user, 'job_not_selected', 'Not this time: {job}', $note ? '{note}' : 'Thank you for applying to {employer}. Keep going.',
            $this->words($application) + ['note' => (string)$note], $this->data($application));
        return $application;
    }

    /**
     * The way forward, in the employer's words: an interview day, time and place, a call, what to bring.
     * Sending again replaces it (a new time). E-Ride does not run the interview.
     */
    public function sendNextStep(User $employerUser, JobApplication $application, string $message, ?string $at, ?string $place,
                                 ?float $lat = null, ?float $lng = null): JobApplication
    {
        $this->asEmployer($employerUser, $application);
        $this->expect($application, [JobApplication::SENT, JobApplication::SEEN, JobApplication::SHORTLISTED, JobApplication::NEXT_STEP]);
        $message = trim(mb_substr($message, 0, 1000));
        if ($message === '') {
            $this->fail('message', translate('Write the next step: when, where, who to ask for, what to bring.'));
        }
        $fee = \Modules\MarketManagement\Lib\JobSettings::firstIn($message, \Modules\MarketManagement\Lib\JobSettings::feeWords());
        if ($fee) {
            $this->fail('message', translate('E-Ride Jobs never asks applicants for money. Remove ":word" from your message.', ['word' => $fee]));
        }
        $when = $at ? Carbon::createFromFormat('Y-m-d H:i', $at) : null;
        if ($when && $when->isPast()) {
            $this->fail('at', translate('Choose a time in the future.'));
        }
        $again = $application->status === JobApplication::NEXT_STEP;
        $application->update([
            'status' => JobApplication::NEXT_STEP, 'next_step' => $message, 'next_step_at' => $when, 'next_step_place' => $place ? trim(mb_substr($place, 0, 191)) : null,
            'next_step_lat' => $lat !== null && $lng !== null ? $lat : null, 'next_step_lng' => $lat !== null && $lng !== null ? $lng : null,
            'next_step_sent_at' => now(), 'next_step_read_at' => null, 'seen_at' => $application->seen_at ?? now(),
            'shortlisted_at' => $application->shortlisted_at ?? now(),
        ]);
        MarketPush::send($application->user, 'job_next_step', $again ? 'New next step from {employer}' : 'Next step from {employer}',
            $when ? '{job}: {when}. Open it for the details.' : '{job}: open it for the details.',
            $this->words($application) + ['when' => $when?->translatedFormat('D j M, H:i') ?? ''], $this->data($application));
        return $application;
    }

    /**
     * The employer says this person got the job. The person confirms in their app (so an employer
     * cannot invent hires). With $closeJob, the job closes as filled and the others are told kindly.
     */
    public function hire(User $employerUser, JobApplication $application, bool $closeJob): JobApplication
    {
        $this->asEmployer($employerUser, $application);
        $this->expect($application, [JobApplication::SENT, JobApplication::SEEN, JobApplication::SHORTLISTED, JobApplication::NEXT_STEP]);
        DB::transaction(function () use ($application) {
            $application->update(['status' => JobApplication::HIRED, 'hired_at' => now(), 'hire_confirmed_at' => null, 'hire_declined_at' => null,
                'decided_at' => now(), 'seen_at' => $application->seen_at ?? now(), 'shortlisted_at' => $application->shortlisted_at ?? now()]);
            JobPost::whereKey($application->job_id)->increment('hired_count');
        });
        MarketPush::send($application->user, 'job_hired', '{employer} says you got the job', '{job}: is that right? Confirm it in the app.',
            $this->words($application), $this->data($application));
        if ($closeJob) {
            app(JobService::class)->close($application->job->fresh('employer'), 'filled');
        }
        return $application;
    }

    /** The person answers: yes, I got the job (counts for the employer), or no (back to where it was). */
    public function answerHire(User $user, JobApplication $application, bool $yes): JobApplication
    {
        $this->asApplicant($user, $application);
        if ($application->status !== JobApplication::HIRED || $application->hire_confirmed_at) {
            $this->fail('application', translate('This application has changed. Pull to refresh.'));
        }
        $employer = $application->job?->employer;
        $name = $this->cardOf($user)?->name ?? trim($user->first_name . ' ' . $user->last_name);
        if ($yes) {
            DB::transaction(function () use ($application, $employer) {
                $application->update(['hire_confirmed_at' => now()]);
                if ($employer) {
                    JobEmployer::whereKey($employer->id)->increment('hires_count');
                }
            });
            MarketPush::send($employer?->user, 'job_hire_confirmed', '{name} confirmed', 'Hired for {job} through E-Ride.',
                $this->words($application) + ['name' => $name], $this->data($application));
        } else {
            DB::transaction(function () use ($application) {
                $application->update(['status' => $application->next_step ? JobApplication::NEXT_STEP : JobApplication::SHORTLISTED,
                    'hire_declined_at' => now(), 'hired_at' => null]);
                JobPost::whereKey($application->job_id)->where('hired_count', '>', 0)->decrement('hired_count');
            });
            MarketPush::send($employer?->user, 'job_hire_declined', '{name} says no', '{name} says they were not hired for {job}. Talk to them if this is a mistake.',
                $this->words($application) + ['name' => $name], $this->data($application));
        }
        return $application;
    }

    // --------------------------------------------------------- helpers

    public function asApplicant(User $user, JobApplication $application): void
    {
        if ($application->user_id !== $user->id) {
            $this->fail('application', translate('This application is not yours.'));
        }
    }

    public function asEmployer(User $user, JobApplication $application): JobEmployer
    {
        $employer = $application->job?->employer;
        if (!$employer || $employer->user_id !== $user->id) {
            $this->fail('application', translate('This application is not for one of your jobs.'));
        }
        return $employer;
    }

    private function expect(JobApplication $application, array $statuses): void
    {
        if (!in_array($application->status, $statuses, true)) {
            $this->fail('application', translate('This application has changed. Pull to refresh.'));
        }
    }

    private function words(JobApplication $application): array
    {
        return ['job' => $application->job?->title, 'employer' => $application->job?->employer?->name];
    }

    private function data(JobApplication $application): array
    {
        return ['job_code' => $application->job?->code, 'application_id' => $application->id];
    }

    private function list(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode((string)$value, true);
        return is_array($decoded) ? $decoded : array_values(array_filter(array_map('trim', explode(',', (string)$value))));
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
