<?php

namespace Modules\PlaceManagement\Http\Controllers\Web\Admin;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\PlaceManagement\Entities\Listing;
use Modules\PlaceManagement\Entities\ListingReport;
use Modules\PlaceManagement\Entities\ListingVerification;
use Modules\PlaceManagement\Lib\ListingSettings;
use Modules\PlaceManagement\Service\ListingService;
use Modules\UserManagement\Entities\User;

/**
 * Admin, Homes & spaces: the review queue, E-Ride staff visits (the visit page
 * is used on the staff member's phone, at the place), reports and the policy.
 */
class ListingAdminController extends Controller
{
    public const TABS = ['review', 'live', 'paused', 'rented', 'closed', 'all'];

    public function __construct(protected ListingService $listings)
    {
    }

    public function index(Request $request): View
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'review';
        $search = trim((string)$request->query('search', ''));
        $query = Listing::with(['owner', 'photos'])->latest();
        match ($tab) {
            'review' => $query->where(fn($q) => $q->where('status', Listing::PENDING)->orWhere('needs_review', 1)),
            'live' => $query->where('status', Listing::LIVE),
            'paused' => $query->where('status', Listing::PAUSED),
            'rented' => $query->where('status', Listing::RENTED),
            'closed' => $query->whereIn('status', [Listing::REFUSED, Listing::REMOVED, Listing::CHANGES]),
            default => null,
        };
        if ($search !== '') {
            $query->where(fn($q) => $q->where('title', 'like', "%{$search}%")->orWhere('neighbourhood', 'like', "%{$search}%")
                ->orWhereHas('owner', fn($o) => $o->where('phone', 'like', "%{$search}%")->orWhere('first_name', 'like', "%{$search}%")));
        }
        $items = $query->paginate(20)->withQueryString();
        $counts = [
            'review' => Listing::where(fn($q) => $q->where('status', Listing::PENDING)->orWhere('needs_review', 1))->count(),
            'visits' => ListingVerification::whereIn('status', [ListingVerification::REQUESTED, ListingVerification::SCHEDULED])->count(),
            'reports' => ListingReport::where('status', ListingReport::OPEN)->count(),
        ];
        return view('placemanagement::admin.listings.index', compact('items', 'tab', 'search', 'counts'));
    }

    public function show(Listing $listing): View
    {
        $listing->load(['owner', 'photos', 'visits.seeker', 'verifications.staff', 'reports.reporter']);
        $staff = $this->staff();
        return view('placemanagement::admin.listings.show', compact('listing', 'staff'));
    }

    // ------------------------------------------------------------- review

    public function approve(Listing $listing): RedirectResponse
    {
        $this->listings->approve($listing);
        Toastr::success(translate('Listing approved. It is live in Homes & spaces.'));
        return back();
    }

    public function reviewed(Listing $listing): RedirectResponse
    {
        $listing->update(['needs_review' => false]);
        Toastr::success(translate('Marked as reviewed'));
        return back();
    }

    public function changes(Request $request, Listing $listing): RedirectResponse
    {
        $note = $request->validate(['note' => 'required|string|max:300'])['note'];
        $this->listings->requestChanges($listing, $note);
        Toastr::success(translate('The owner was asked for changes'));
        return back();
    }

    public function refuse(Request $request, Listing $listing): RedirectResponse
    {
        $note = $request->validate(['note' => 'required|string|max:300'])['note'];
        $this->listings->refuse($listing, $note);
        Toastr::success(translate('Listing refused'));
        return back();
    }

    public function remove(Request $request, Listing $listing): RedirectResponse
    {
        $data = $request->validate(['note' => 'required|string|max:300', 'block' => 'nullable|boolean']);
        $this->listings->remove($listing, $data['note'], $request->boolean('block'));
        Toastr::success($request->boolean('block') ? translate('Listing removed and owner blocked') : translate('Listing removed'));
        return back();
    }

    // ------------------------------------------------------------- staff visits

    public function visits(): View
    {
        $open = ListingVerification::with(['listing.owner', 'staff'])
            ->whereIn('status', [ListingVerification::REQUESTED, ListingVerification::SCHEDULED])
            ->orderByRaw("status = 'scheduled' desc")->orderBy('scheduled_for')->oldest()->get();
        $done = ListingVerification::with(['listing', 'staff'])->whereNotNull('completed_at')->latest('completed_at')->limit(20)->get();
        $staff = $this->staff();
        return view('placemanagement::admin.listings.visits', compact('open', 'done', 'staff'));
    }

    public function assign(Request $request, ListingVerification $verification): RedirectResponse
    {
        $data = $request->validate([
            'staff_id' => ['required', Rule::exists('users', 'id')->whereIn('user_type', ADMIN_USER_TYPES)],
            'scheduled_for' => 'required|date|after:now',
        ]);
        $this->listings->schedule($verification, $data['staff_id'], $data['scheduled_for']);
        Toastr::success(translate('Visit booked. The owner was told.'));
        return back();
    }

    /** The page a staff member opens on their phone, at the place. */
    public function visitPage(ListingVerification $verification): View
    {
        $verification->load(['listing.owner', 'listing.photos']);
        $radius = ListingSettings::all()['visit_radius_m'];
        return view('placemanagement::admin.listings.visit', compact('verification', 'radius'));
    }

    public function visitSubmit(Request $request, ListingVerification $verification): RedirectResponse
    {
        if (!$verification->isOpen()) {
            Toastr::error(translate('This visit is already done.'));
            return redirect()->route('admin.listing.visits');
        }
        $image = 'image|mimes:' . str_replace(['.', ' '], '', IMAGE_ACCEPTED_EXTENSIONS) . '|max:' . convertBytesToKiloBytes(maxUploadSize('image'));
        $data = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'checks' => 'nullable|array',
            'result' => ['required', Rule::in(['pass', 'fixable', 'fake'])],
            'note' => 'required_unless:result,pass|nullable|string|max:500',
            'photos' => 'required_if:result,pass|nullable|array|max:' . Listing::MAX_PHOTOS,
            'photos.*' => $image,
        ], [
            'latitude.required' => translate('Your location is needed. Allow location for this page and try again.'),
            'photos.required_if' => translate('Take the photos on site before passing the visit.'),
        ]);
        $checks = [];
        foreach (ListingVerification::CHECKS as $check) {
            $checks[$check] = !empty($data['checks'][$check]);
        }
        if ($data['result'] === 'pass' && in_array(false, $checks, true)) {
            return back()->withErrors(['checks' => translate('Every check must pass before the place is Verified. Use "Things to correct" otherwise.')])->withInput();
        }
        try {
            $this->listings->completeVerification($verification, auth()->user(), (float)$data['latitude'], (float)$data['longitude'], $checks,
                $data['result'], $data['note'] ?? null, $request->file('photos', []));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }
        Toastr::success(match ($data['result']) {
            'pass' => translate('Verified. The owner was told.'),
            'fixable' => translate('The owner was sent the things to correct.'),
            default => translate('Listing removed and owner blocked.'),
        });
        return redirect()->route('admin.listing.visits');
    }

    // ------------------------------------------------------------- reports

    public function reports(Request $request): View
    {
        $status = in_array($request->query('status'), ['open', 'closed'], true) ? $request->query('status') : 'open';
        $reports = ListingReport::with(['listing.owner', 'reporter'])
            ->when($status === 'open', fn($q) => $q->where('status', ListingReport::OPEN), fn($q) => $q->where('status', '!=', ListingReport::OPEN))
            ->latest()->paginate(20)->withQueryString();
        return view('placemanagement::admin.listings.reports', compact('reports', 'status'));
    }

    public function decide(Request $request, ListingReport $report): RedirectResponse
    {
        $decision = $request->validate(['decision' => ['required', Rule::in([ListingReport::UPHELD, ListingReport::BLOCKED, ListingReport::DISMISSED])]])['decision'];
        if ($report->status !== ListingReport::OPEN) {
            Toastr::error(translate('This report was already decided.'));
            return back();
        }
        $this->listings->decideReport($report, $decision, auth()->user());
        Toastr::success(translate('Decision saved'));
        return back();
    }

    // ------------------------------------------------------------- settings

    public function settings(): View
    {
        $r = ListingSettings::reservations();
        $settings = ListingSettings::all() + [
            'reservations_enabled' => $r['enabled'], 'reservation_min' => $r['min'], 'reservation_max' => $r['max'],
            'reservation_fee_percent' => $r['fee_percent'], 'reservation_pay_minutes' => $r['pay_minutes'],
            'reservation_accept_hours' => $r['accept_hours'], 'reservation_release_days' => $r['release_days'],
            'reservation_move_in_days' => $r['move_in_days'],
        ];
        return view('placemanagement::admin.listings.settings', compact('settings'));
    }

    public function saveSettings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'confirm_days_short' => 'required|integer|min:1|max:120',
            'confirm_days_long' => 'required|integer|min:1|max:180',
            'late_after_days' => 'required|integer|min:0|max:30',
            'verified_months' => 'required|integer|min:1|max:24',
            'stale_days' => 'required|integer|min:7|max:365',
            'taken_reports' => 'required|integer|min:1|max:10',
            'taken_hours' => 'required|integer|min:6|max:168',
            'strike_limit' => 'required|integer|min:1|max:10',
            'strike_months' => 'required|integer|min:1|max:24',
            'ban_days' => 'required|integer|min:1|max:365',
            'visit_radius_m' => 'required|integer|min:20|max:1000',
            'neighbourhoods' => 'required|string|max:2000',
            'landmark_name' => 'nullable|string|max:60',
            'landmark_latitude' => 'nullable|required_with:landmark_name|numeric|between:-90,90',
            'landmark_longitude' => 'nullable|required_with:landmark_name|numeric|between:-180,180',
            // Reservation money.
            'reservations_enabled' => 'nullable|boolean',
            'reservation_min' => 'required|numeric|min:100',
            'reservation_max' => 'required|numeric|gte:reservation_min',
            'reservation_fee_percent' => 'required|numeric|min:0|max:50',
            'reservation_pay_minutes' => 'required|integer|min:5|max:120',
            'reservation_accept_hours' => 'required|integer|min:1|max:168',
            'reservation_release_days' => 'required|integer|min:1|max:30',
            'reservation_move_in_days' => 'required|integer|min:1|max:90',
        ]);
        $data['reservations_enabled'] = $request->boolean('reservations_enabled') ? '1' : '0';
        foreach ($data as $key => $value) {
            updateBusinessSetting(keyName: 'listing_' . $key, settingType: PLACE_SETTINGS, value: trim((string)$value));
        }
        Cache::forget(CACHE_BUSINESS_SETTINGS);
        Toastr::success(translate('Homes & spaces settings saved'));
        return back();
    }

    private function staff()
    {
        return User::whereIn('user_type', ADMIN_USER_TYPES)->where('is_active', 1)->orderBy('first_name')->get(['id', 'first_name', 'last_name']);
    }
}
