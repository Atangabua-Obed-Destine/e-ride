<?php

namespace Modules\MarketManagement\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\MarketManagement\Entities\JobApplication;
use Modules\MarketManagement\Entities\JobEmployer;
use Modules\MarketManagement\Entities\JobPost;
use Modules\MarketManagement\Entities\JobReport;
use Modules\MarketManagement\Entities\JobWorkCard;
use Modules\MarketManagement\Lib\JobSettings;
use Modules\MarketManagement\Service\JobApplicationService;
use Modules\MarketManagement\Service\JobService;
use Modules\MarketManagement\Transformers\JobPresenter;

/** Customer app, E-Ride Jobs for job seekers: find jobs, the Work Card, apply, my applications, alerts. */
class JobController extends Controller
{
    public function __construct(protected JobService $jobs, protected JobApplicationService $applications)
    {
    }

    /** Switches, lists, and what needs the person's attention. */
    public function meta(Request $request): JsonResponse
    {
        $user = $request->user();
        $settings = JobSettings::all();
        $employer = $this->jobs->employerOf($user);
        $card = $this->applications->cardOf($user);
        $mine = JobApplication::where('user_id', $user->id);
        return response()->json(responseFormatter(DEFAULT_200, [
            'enabled' => $settings['enabled'],
            'categories' => JobPost::CATEGORIES,
            'types' => JobPost::TYPES,
            'periods' => JobPost::PERIODS,
            'report_reasons' => JobReport::REASONS,
            'doc_types' => JobEmployer::DOC_TYPES,
            'link_types' => JobWorkCard::LINK_TYPES,
            'max_questions' => JobPost::MAX_QUESTIONS,
            'review_hours' => $settings['review_hours'],
            'stale_days' => $settings['stale_days'],
            'card_percent' => $card?->percent() ?? 0,
            'card_can_apply' => (bool)$card?->canApply(),
            'next_steps_unread' => (clone $mine)->where('status', JobApplication::NEXT_STEP)->whereNull('next_step_read_at')->count(),
            'applications_open' => (clone $mine)->whereIn('status', JobApplication::ACTIVE)->count(),
            'hires_to_confirm' => (clone $mine)->where('status', JobApplication::HIRED)->whereNull('hire_confirmed_at')->count(),
            'employer' => JobPresenter::employer($employer, true),
            'new_applicants' => $employer ? JobApplication::whereIn('job_id', $employer->jobs()->select('id'))->where('status', JobApplication::SENT)->count() : 0,
            'alert_on' => (bool)$this->jobs->alertOf($user)?->enabled,
        ]));
    }

    /** ?q=&category=&type=&saved=1&page= */
    public function feed(Request $request): JsonResponse
    {
        $request->validate([
            'q' => 'nullable|string|max:80',
            'category' => ['nullable', Rule::in(JobPost::CATEGORIES)],
            'type' => ['nullable', Rule::in(JobPost::TYPES)],
            'saved' => 'nullable|boolean',
            'page' => 'nullable|integer|min:1',
        ]);
        if (!JobSettings::enabled()) {
            return $this->off();
        }
        $page = $this->jobs->feed($request->user(), $request->only(['q', 'category', 'type', 'saved']), (int)$request->input('page', 1));
        $applied = JobApplication::where('user_id', $request->user()->id)->whereIn('job_id', collect($page->items())->pluck('id'))
            ->where('status', '!=', JobApplication::WITHDRAWN)->pluck('status', 'job_id');
        return response()->json(responseFormatter(DEFAULT_200, [
            'jobs' => collect($page->items())->map(fn(JobPost $j) => JobPresenter::job($j, $request->user()->id) + ['applied' => $applied[$j->id] ?? null])->all(),
            'page' => $page->currentPage(),
            'has_more' => $page->hasMorePages(),
            'total' => $page->total(),
        ]));
    }

    public function show(Request $request, string $code): JsonResponse
    {
        $user = $request->user();
        $job = $this->jobs->find($code, $user);
        if (!$job) {
            return $this->fail('job', translate('This job is no longer on E-Ride Jobs.'), 404);
        }
        $this->jobs->countView($job, $user);
        $application = JobApplication::where('job_id', $job->id)->where('user_id', $user->id)->first();
        return response()->json(responseFormatter(DEFAULT_200, JobPresenter::job($job, $user->id, true, $application)));
    }

    public function save(Request $request, string $code): JsonResponse
    {
        $job = $this->jobs->find($code, $request->user());
        if (!$job) {
            return $this->fail('job', translate('This job is no longer on E-Ride Jobs.'), 404);
        }
        return response()->json(responseFormatter(DEFAULT_200, ['saved' => $this->jobs->toggleSave($request->user(), $job)]));
    }

    public function report(Request $request, string $code): JsonResponse
    {
        $request->validate(['reason' => ['required', Rule::in(JobReport::REASONS)], 'note' => 'nullable|string|max:300']);
        $job = $this->jobs->find($code, $request->user());
        if (!$job) {
            return $this->fail('job', translate('This job is no longer on E-Ride Jobs.'), 404);
        }
        $this->jobs->report($request->user(), $job, $request->input('reason'), $request->input('note'));
        return response()->json(responseFormatter(DEFAULT_200, ['reported' => true]));
    }

    // --------------------------------------------------------- Work Card

    public function card(Request $request): JsonResponse
    {
        $user = $request->user();
        $card = $this->applications->cardOf($user);
        return response()->json(responseFormatter(DEFAULT_200, [
            'card' => JobPresenter::card($card),
            // To prefill a new card.
            'suggested_name' => trim($user->first_name . ' ' . $user->last_name),
        ]));
    }

    public function saveCard(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'nullable|string|max:80',
            'area_label' => 'nullable|string|max:80',
            'languages' => 'nullable|string|max:80',
            'headline' => 'nullable|string|max:120',
            'education' => 'nullable|string|max:300',
            'skills' => 'nullable',
            'experience' => 'nullable',
            'looking_for' => 'nullable',
            // A PDF, or a photo of a paper CV.
            'cv' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:' . min(10240, convertBytesToKiloBytes(maxUploadSize('file'))),
            'remove_cv' => 'nullable|boolean',
            'links' => 'nullable',
            // The 30-second voice intro, as the phone records it.
            'voice' => 'nullable|file|mimes:m4a,mp4,aac,mp3,mpga,ogg,oga,wav,webm,3gp|max:4096',
            'voice_seconds' => 'nullable|integer|min:1|max:60',
            'remove_voice' => 'nullable|boolean',
        ]);
        $card = $this->applications->saveCard($request->user(), $request->except(['cv', 'remove_cv', 'voice', 'remove_voice']), $request->file('cv'),
            $request->boolean('remove_cv'), $request->file('voice'), $request->boolean('remove_voice'));
        return response()->json(responseFormatter(DEFAULT_200, ['card' => JobPresenter::card($card)]));
    }

    // --------------------------------------------------------- applying

    public function apply(Request $request, string $code): JsonResponse
    {
        $request->validate(['answers' => 'nullable', 'message' => 'nullable|string|max:600', 'share_phone' => 'nullable|boolean']);
        $job = $this->jobs->find($code, $request->user());
        if (!$job) {
            return $this->fail('job', translate('This job is no longer on E-Ride Jobs.'), 404);
        }
        $answers = $request->input('answers', []);
        $answers = is_array($answers) ? $answers : (json_decode((string)$answers, true) ?: explode(',', (string)$answers));
        $application = $this->applications->apply($request->user(), $job, array_values($answers), $request->input('message'), $request->boolean('share_phone'));
        return response()->json(responseFormatter(DEFAULT_200, JobPresenter::forApplicant($application)));
    }

    public function applications(Request $request): JsonResponse
    {
        return response()->json(responseFormatter(DEFAULT_200, [
            'applications' => $this->applications->mine($request->user())->map(fn($a) => JobPresenter::forApplicant($a))->all(),
        ]));
    }

    public function application(Request $request, string $id): JsonResponse
    {
        $application = $this->mine($request, $id);
        if ($application instanceof JsonResponse) {
            return $application;
        }
        $this->applications->markNextStepRead($request->user(), $application);
        return response()->json(responseFormatter(DEFAULT_200, JobPresenter::forApplicant($application->fresh(['job.employer']))));
    }

    /** yes=1: I got the job; yes=0: I did not. */
    public function answerHire(Request $request, string $id): JsonResponse
    {
        $request->validate(['yes' => 'required|boolean']);
        $application = $this->mine($request, $id);
        if ($application instanceof JsonResponse) {
            return $application;
        }
        $this->applications->answerHire($request->user(), $application, $request->boolean('yes'));
        return response()->json(responseFormatter(DEFAULT_200, JobPresenter::forApplicant($application->fresh(['job.employer']))));
    }

    public function withdraw(Request $request, string $id): JsonResponse
    {
        $application = $this->mine($request, $id);
        if ($application instanceof JsonResponse) {
            return $application;
        }
        $this->applications->withdraw($request->user(), $application);
        return response()->json(responseFormatter(DEFAULT_200, JobPresenter::forApplicant($application->fresh(['job.employer']))));
    }

    // --------------------------------------------------------- alerts

    public function alert(Request $request): JsonResponse
    {
        $alert = $this->jobs->alertOf($request->user());
        return response()->json(responseFormatter(DEFAULT_200, [
            'enabled' => (bool)$alert?->enabled,
            'categories' => array_values((array)($alert?->categories ?? [])),
            'types' => array_values((array)($alert?->types ?? [])),
            'per_day' => JobSettings::all()['alerts_per_day'],
        ]));
    }

    public function saveAlert(Request $request): JsonResponse
    {
        $request->validate(['categories' => 'nullable', 'types' => 'nullable', 'enabled' => 'nullable|boolean']);
        $list = fn($v) => is_array($v) ? $v : array_values(array_filter(explode(',', (string)$v)));
        $alert = $this->jobs->saveAlert($request->user(), $list($request->input('categories', [])), $list($request->input('types', [])),
            $request->boolean('enabled', true));
        return response()->json(responseFormatter(DEFAULT_200, [
            'enabled' => $alert->enabled, 'categories' => $alert->categories, 'types' => $alert->types,
        ]));
    }

    // --------------------------------------------------------- helpers

    private function mine(Request $request, string $id): JobApplication|JsonResponse
    {
        $application = JobApplication::with('job.employer')->find($id);
        if (!$application || $application->user_id !== $request->user()->id) {
            return $this->fail('application', translate('This application is not yours.'), 404);
        }
        return $application;
    }

    private function off(): JsonResponse
    {
        return $this->fail('jobs', translate('E-Ride Jobs is not open yet.'), 403);
    }

    private function fail(string $code, string $message, int $status = 403): JsonResponse
    {
        return response()->json(responseFormatter(DEFAULT_400, errors: [['error_code' => $code, 'message' => $message]]), $status);
    }
}
