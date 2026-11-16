<?php

namespace Modules\MarketManagement\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\MarketManagement\Entities\JobApplication;
use Modules\MarketManagement\Entities\JobEmployer;
use Modules\MarketManagement\Entities\JobPost;
use Modules\MarketManagement\Lib\JobSettings;
use Modules\MarketManagement\Service\JobApplicationService;
use Modules\MarketManagement\Service\JobService;
use Modules\MarketManagement\Transformers\JobPresenter;

/** Customer app, E-Ride Jobs for employers: the employer check, posting jobs, applicants and the next step. */
class JobEmployerController extends Controller
{
    public function __construct(protected JobService $jobs, protected JobApplicationService $applications)
    {
    }

    public function employer(Request $request): JsonResponse
    {
        return response()->json(responseFormatter(DEFAULT_200, ['employer' => JobPresenter::employer($this->jobs->employerOf($request->user()), true)]));
    }

    public function saveEmployer(Request $request): JsonResponse
    {
        $image = 'image|max:' . convertBytesToKiloBytes(maxUploadSize('image'));
        $request->validate([
            'kind' => ['nullable', Rule::in(JobEmployer::KINDS)],
            'name' => 'nullable|string|max:120',
            'about' => 'nullable|string|max:600',
            'area_label' => 'nullable|string|max:80',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'address' => 'nullable|string|max:191',
            'doc_type' => ['nullable', Rule::in(JobEmployer::DOC_TYPES)],
            'logo' => 'nullable|' . $image,
            'doc' => 'nullable|' . $image,
        ]);
        if (!JobSettings::enabled()) {
            return $this->fail('jobs', translate('E-Ride Jobs is not open yet.'));
        }
        $employer = $this->jobs->saveEmployer($request->user(), $request->except(['logo', 'doc']), $request->file('logo'), $request->file('doc'));
        return response()->json(responseFormatter(DEFAULT_200, ['employer' => JobPresenter::employer($employer, true)]));
    }

    /** My jobs, newest first, with the new applications on each. */
    public function jobs(Request $request): JsonResponse
    {
        $employer = $this->jobs->employerOf($request->user());
        $jobs = $employer ? $employer->jobs()->with(['employer', 'applications:id,job_id,status'])->latest()->limit(100)->get() : collect();
        return response()->json(responseFormatter(DEFAULT_200, [
            'jobs' => $jobs->map(fn(JobPost $j) => JobPresenter::job($j, $request->user()->id, true))->all(),
        ]));
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate($this->rules(true));
        $employer = $this->jobs->employerOf($request->user());
        if (!$employer) {
            return $this->fail('employer', translate('Tell us who you are first: it takes 2 minutes, once.'));
        }
        if (!JobSettings::enabled()) {
            return $this->fail('jobs', translate('E-Ride Jobs is not open yet.'));
        }
        $job = $this->jobs->create($employer, $request->all());
        return response()->json(responseFormatter(DEFAULT_200, JobPresenter::job($job, $request->user()->id, true)));
    }

    public function update(Request $request, string $code): JsonResponse
    {
        $request->validate($this->rules(false));
        $job = $this->ownJob($request, $code);
        if ($job instanceof JsonResponse) {
            return $job;
        }
        $job = $this->jobs->update($job, $request->all());
        return response()->json(responseFormatter(DEFAULT_200, JobPresenter::job($job, $request->user()->id, true)));
    }

    public function close(Request $request, string $code): JsonResponse
    {
        $job = $this->ownJob($request, $code);
        if ($job instanceof JsonResponse) {
            return $job;
        }
        $this->jobs->close($job, 'employer');
        return response()->json(responseFormatter(DEFAULT_200, JobPresenter::job($job->fresh('employer'), $request->user()->id, true)));
    }

    /** ?tab=new|shortlist|other, best match first. */
    public function applicants(Request $request, string $code): JsonResponse
    {
        $request->validate(['tab' => 'nullable|in:new,shortlist,other']);
        $job = $this->ownJob($request, $code);
        if ($job instanceof JsonResponse) {
            return $job;
        }
        $counts = JobApplication::where('job_id', $job->id)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        return response()->json(responseFormatter(DEFAULT_200, [
            'job' => JobPresenter::job($job, $request->user()->id, true),
            'applicants' => $this->applications->applicants($job, $request->input('tab', 'new'))->map(fn($a) => JobPresenter::forEmployer($a))->all(),
            'counts' => [
                'new' => (int)($counts[JobApplication::SENT] ?? 0) + (int)($counts[JobApplication::SEEN] ?? 0),
                'shortlist' => (int)($counts[JobApplication::SHORTLISTED] ?? 0) + (int)($counts[JobApplication::NEXT_STEP] ?? 0) + (int)($counts[JobApplication::HIRED] ?? 0),
                'other' => (int)($counts[JobApplication::NOT_SELECTED] ?? 0) + (int)($counts[JobApplication::CLOSED] ?? 0),
            ],
        ]));
    }

    /** One applicant; opening it marks it seen. */
    public function applicant(Request $request, string $id): JsonResponse
    {
        $application = $this->forMe($request, $id);
        if ($application instanceof JsonResponse) {
            return $application;
        }
        $this->applications->open($request->user(), $application);
        return $this->applicantOut($request, $application);
    }

    public function shortlist(Request $request, string $id): JsonResponse
    {
        return $this->act($request, $id, fn($a) => $this->applications->shortlist($request->user(), $a));
    }

    public function notSelected(Request $request, string $id): JsonResponse
    {
        $request->validate(['note' => 'nullable|string|max:400']);
        return $this->act($request, $id, fn($a) => $this->applications->notSelected($request->user(), $a, $request->input('note')));
    }

    public function nextStep(Request $request, string $id): JsonResponse
    {
        $request->validate(['message' => 'required|string|max:1000', 'at' => 'nullable|date_format:Y-m-d H:i', 'place' => 'nullable|string|max:191',
            'lat' => 'nullable|numeric|between:-90,90', 'lng' => 'nullable|numeric|between:-180,180']);
        return $this->act($request, $id, fn($a) => $this->applications->sendNextStep($request->user(), $a, $request->input('message'),
            $request->input('at'), $request->input('place'), $request->filled('lat') ? (float)$request->input('lat') : null,
            $request->filled('lng') ? (float)$request->input('lng') : null));
    }

    /** close=1: the job is filled, close it and tell the others kindly. */
    public function hire(Request $request, string $id): JsonResponse
    {
        $request->validate(['close' => 'nullable|boolean']);
        return $this->act($request, $id, fn($a) => $this->applications->hire($request->user(), $a, $request->boolean('close')));
    }

    // --------------------------------------------------------- helpers

    private function act(Request $request, string $id, callable $do): JsonResponse
    {
        $application = $this->forMe($request, $id);
        if ($application instanceof JsonResponse) {
            return $application;
        }
        $do($application);
        return $this->applicantOut($request, $application->fresh(['job.employer', 'user']));
    }

    private function applicantOut(Request $request, JobApplication $application): JsonResponse
    {
        $application->fit = $this->applications->fit($application->job, $application, $this->applications->cardOf($application->user));
        return response()->json(responseFormatter(DEFAULT_200, JobPresenter::forEmployer($application, true)));
    }

    private function forMe(Request $request, string $id): JobApplication|JsonResponse
    {
        $application = JobApplication::with(['job.employer', 'user'])->find($id);
        if (!$application || $application->job?->employer?->user_id !== $request->user()->id || $application->status === JobApplication::WITHDRAWN) {
            return $this->fail('application', translate('This application is not for one of your jobs.'), 404);
        }
        return $application;
    }

    private function ownJob(Request $request, string $code): JobPost|JsonResponse
    {
        $job = JobPost::with('employer')->where('code', $code)->first();
        if (!$job || $job->employer?->user_id !== $request->user()->id) {
            return $this->fail('job', translate('This job is not yours.'), 404);
        }
        return $job;
    }

    private function rules(bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';
        return [
            'category' => [$req, Rule::in(JobPost::CATEGORIES)],
            'type' => [$req, Rule::in(JobPost::TYPES)],
            'title' => "$req|string|min:3|max:120",
            'description' => "$req|string|min:20|max:4000",
            'requirements' => 'nullable|string|max:600',
            'skills' => 'nullable',
            'pay_min' => "$req|numeric|min:1|max:100000000",
            'pay_max' => 'nullable|numeric|min:0|max:100000000',
            'pay_period' => [$req, Rule::in(JobPost::PERIODS)],
            'schedule' => 'nullable|string|max:120',
            'places' => 'nullable|integer|min:1|max:500',
            'area_label' => "$req|string|max:80",
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'address' => 'nullable|string|max:191',
            'questions' => 'nullable',
            'required_links' => 'nullable',
        ];
    }

    private function fail(string $code, string $message, int $status = 403): JsonResponse
    {
        return response()->json(responseFormatter(DEFAULT_400, errors: [['error_code' => $code, 'message' => $message]]), $status);
    }
}
