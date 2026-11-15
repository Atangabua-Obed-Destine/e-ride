<?php

namespace Modules\MarketManagement\Http\Controllers\Web\Admin;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\MarketManagement\Entities\JobApplication;
use Modules\MarketManagement\Entities\JobEmployer;
use Modules\MarketManagement\Entities\JobPost;
use Modules\MarketManagement\Entities\JobReport;
use Modules\MarketManagement\Lib\JobSettings;
use Modules\MarketManagement\Service\JobService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Admin panel, sidebar "E-Ride Jobs": jobs to check, employers to verify, reports, settings. */
class JobAdminController extends Controller
{
    public function __construct(protected JobService $jobs)
    {
    }

    /** ?tab=review|live|closed&job=<id>: the queue (oldest first) and one job open on the right. */
    public function index(Request $request): View
    {
        $tab = in_array($request->input('tab'), ['review', 'live', 'closed'], true) ? $request->input('tab') : 'review';
        $statuses = match ($tab) {
            'live' => [JobPost::LIVE],
            'closed' => [JobPost::CLOSED, JobPost::REJECTED, JobPost::SENT_BACK],
            default => [JobPost::REVIEW, JobPost::HIDDEN],
        };
        $query = JobPost::with('employer')->whereIn('status', $statuses);
        $jobs = ($tab === 'review' ? $query->orderBy('submitted_at') : $query->latest('updated_at'))->paginate(25)->withQueryString();
        $open = $request->input('job') ? JobPost::with('employer.user')->find($request->input('job')) : $jobs->first();
        return view('marketmanagement::admin.jobs', [
            'tab' => $tab,
            'jobs' => $jobs,
            'open' => $open,
            'pay' => fn(JobPost $j) => $this->jobs->payText($j),
            'reports' => $open ? JobReport::with('reporter')->where('job_id', $open->id)->latest()->get() : collect(),
            'stats' => [
                'review' => JobPost::whereIn('status', [JobPost::REVIEW, JobPost::HIDDEN])->count(),
                'live' => JobPost::where('status', JobPost::LIVE)->count(),
                'employers_waiting' => JobEmployer::where('status', JobEmployer::PENDING)->count(),
                'applications_30' => JobApplication::where('created_at', '>=', now()->subDays(30))->count(),
                'next_steps_30' => JobApplication::where('next_step_sent_at', '>=', now()->subDays(30))->count(),
                'reports_open' => JobReport::where('status', 'open')->count(),
            ],
            'settings' => JobSettings::all(),
        ]);
    }

    public function decide(Request $request, JobPost $job): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'send_back', 'reject', 'close'])],
            'reason' => 'nullable|string|max:300',
            'freeze' => 'nullable|boolean',
        ]);
        try {
            $adminId = $request->user()?->id;
            match ($data['decision']) {
                'approve' => $this->jobs->approve($job, $adminId),
                'send_back' => $this->jobs->sendBack($job, $this->reason($data), $adminId),
                'reject' => $this->jobs->reject($job, $this->reason($data), $request->boolean('freeze'), $adminId),
                'close' => $this->jobs->close($job, 'removed'),
            };
            JobReport::where('job_id', $job->id)->where('status', 'open')->update(['status' => $data['decision'] === 'approve' ? 'kept' : 'removed']);
        } catch (ValidationException $e) {
            Toastr::error(collect($e->errors())->flatten()->first());
            return back();
        }
        Toastr::success(translate('Saved'));
        return redirect()->route('admin.jobs.index', ['tab' => $request->input('tab', 'review')]);
    }

    /** ?status=pending|verified|rejected|frozen */
    public function employers(Request $request): View
    {
        $status = in_array($request->input('status'), ['pending', 'verified', 'rejected', 'frozen'], true) ? $request->input('status') : 'pending';
        return view('marketmanagement::admin.job-employers', [
            'status' => $status,
            'employers' => JobEmployer::with('user')->withCount('jobs')->where('status', $status)->oldest('updated_at')->paginate(25)->withQueryString(),
            'counts' => JobEmployer::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function decideEmployer(Request $request, JobEmployer $employer): RedirectResponse
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['verify', 'refuse', 'freeze'])],
            'note' => 'nullable|string|max:300',
            'doc_seen' => 'nullable|boolean',
            'called' => 'nullable|boolean',
        ]);
        $adminId = $request->user()?->id;
        if ($data['decision'] === 'verify') {
            if (!$request->boolean('doc_seen') || !$request->boolean('called')) {
                Toastr::error(translate('Tick both checks before verifying.'));
                return back();
            }
            $this->jobs->verifyEmployer($employer, $adminId);
        } elseif ($data['decision'] === 'refuse') {
            $this->jobs->refuseEmployer($employer, $this->reason($data, 'note'), $adminId);
        } else {
            $this->jobs->freezeEmployer($employer, $this->reason($data, 'note'), $adminId);
        }
        Toastr::success(translate('Saved'));
        return back();
    }

    /** The employer's document, for staff only. */
    public function document(JobEmployer $employer): BinaryFileResponse
    {
        $path = $employer->docPath();
        abort_unless($path && is_file($path), 404);
        return response()->file($path, ['Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex']);
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => 'nullable|boolean',
            'job_days' => 'required|integer|min:3|max:90',
            'max_open_jobs' => 'required|integer|min:1|max:100',
            'stale_days' => 'required|integer|min:3|max:90',
            'alerts_per_day' => 'required|integer|min:1|max:10',
            'min_month_pay' => 'required|integer|min:0|max:10000000',
            'review_hours' => 'required|integer|min:1|max:72',
            'hide_after_reports' => 'required|integer|min:1|max:20',
            'fee_words' => 'nullable|string|max:5000',
        ]);
        $data['enabled'] = $request->boolean('enabled') ? 1 : 0;
        foreach ($data as $key => $value) {
            updateBusinessSetting(keyName: 'jobs_' . $key, settingType: JobSettings::TYPE, value: (string)($value ?? ''));
        }
        Cache::forget(CACHE_BUSINESS_SETTINGS);
        Toastr::success(translate('Settings saved'));
        return back();
    }

    private function reason(array $data, string $key = 'reason'): string
    {
        $reason = trim((string)($data[$key] ?? ''));
        if ($reason === '') {
            throw ValidationException::withMessages([$key => translate('Write the reason: the employer reads it.')]);
        }
        return $reason;
    }
}
