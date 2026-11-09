<?php

namespace Modules\PlaceManagement\Http\Controllers\Api\Customer;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\PlaceManagement\Entities\Listing;
use Modules\PlaceManagement\Entities\ListingAlert;
use Modules\PlaceManagement\Entities\ListingReport;
use Modules\PlaceManagement\Entities\ListingVisit;
use Modules\PlaceManagement\Lib\ListingSettings;
use Modules\PlaceManagement\Service\ListingService;
use Modules\PlaceManagement\Transformers\ListingPresenter;

/** Customer app, Homes & spaces: searching, listing a place, owners' actions, reports and alerts. */
class ListingController extends Controller
{
    public function __construct(protected ListingService $listings)
    {
    }

    /** What the screens need before anything else: the choices and the owner's standing. */
    public function meta(Request $request): JsonResponse
    {
        $settings = ListingSettings::all();
        return response()->json(responseFormatter(DEFAULT_200, [
            'types' => Listing::TYPES,
            'short_types' => Listing::SHORT_TYPES,
            'neighbourhoods' => $settings['neighbourhoods'],
            'water' => Listing::WATER,
            'power' => Listing::POWER,
            'amenities' => Listing::AMENITIES,
            'windows' => ListingVisit::WINDOWS,
            'report_reasons' => ListingReport::REASONS,
            'min_photos' => Listing::MIN_PHOTOS,
            'max_photos' => Listing::MAX_PHOTOS,
            'confirm_days_short' => $settings['confirm_days_short'],
            'confirm_days_long' => $settings['confirm_days_long'],
            'late_after_days' => $settings['late_after_days'],
            'strike_limit' => $settings['strike_limit'],
            'ban_days' => $settings['ban_days'],
            'landmark_name' => $settings['landmark_name'] ?: null,
            'cannot_list_reason' => $this->listings->cannotListReason($request->user()),
        ]));
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['nullable', Rule::in(Listing::TYPES)],
            'neighbourhood' => 'nullable|string|max:60',
            'max_rent' => 'nullable|numeric|min:0',
            'verified' => 'nullable|boolean',
            'water' => 'nullable|boolean',
            'backup_power' => 'nullable|boolean',
            'amenities' => 'nullable|string|max:300',   // "wifi,cctv": has all of them
            'limit' => 'nullable|integer|min:1|max:50',
            'offset' => 'nullable|integer|min:1',
        ]);
        $limit = (int)$request->query('limit', 20);
        $offset = ((int)$request->query('offset', 1) - 1) * $limit;
        $result = $this->listings->search($request->only(['type', 'neighbourhood', 'max_rent', 'verified', 'water', 'backup_power', 'amenities']), $offset, $limit);
        $viewer = $request->user()->id;
        return response()->json(responseFormatter(DEFAULT_200, [
            'total' => $result['total'],
            'items' => $result['items']->map(fn(Listing $l) => ListingPresenter::listing($l, $viewer))->all(),
        ]));
    }

    public function mine(Request $request): JsonResponse
    {
        $viewer = $request->user()->id;
        $items = Listing::with('photos')->where('owner_id', $viewer)->latest()->get()
            ->map(fn(Listing $l) => ListingPresenter::listing($l, $viewer))->all();
        return response()->json(responseFormatter(DEFAULT_200, ['items' => $items]));
    }

    public function show(Request $request, Listing $listing): JsonResponse
    {
        $viewer = $request->user()->id;
        $mine = $listing->owner_id === $viewer;
        if (!$mine && !in_array($listing->status, [Listing::LIVE, Listing::RENTED], true)) {
            return $this->fail('listing', translate('This place is no longer available.'), 404);
        }
        if (!$mine) {
            $listing->increment('views');
        }
        return response()->json(responseFormatter(DEFAULT_200, ListingPresenter::listing($listing->load('photos'), $viewer)));
    }

    public function store(Request $request): JsonResponse
    {
        if ($reason = $this->listings->cannotListReason($request->user())) {
            return $this->fail('owner', $reason);
        }
        $validator = Validator::make($request->all(), $this->rules(true));
        if ($validator->fails()) {
            return response()->json(responseFormatter(DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        $listing = $this->listings->create($request->user(), $validator->validated(), $request->file('photos', []));
        return response()->json(responseFormatter(DEFAULT_STORE_200, ListingPresenter::listing($listing, $request->user()->id)));
    }

    public function update(Request $request, Listing $listing): JsonResponse
    {
        if ($listing->owner_id !== $request->user()->id || in_array($listing->status, [Listing::RENTED, Listing::REMOVED], true)) {
            return $this->fail('listing', translate('This listing can no longer be changed.'));
        }
        $validator = Validator::make($request->all(), $this->rules(false));
        if ($validator->fails()) {
            return response()->json(responseFormatter(DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        try {
            $listing = $this->listings->update($listing, $validator->validated(), $request->file('photos', []), array_map('intval', (array)$request->input('remove_photos', [])));
        } catch (ValidationException $e) {
            return $this->failValidation($e);
        }
        return response()->json(responseFormatter(DEFAULT_UPDATE_200, ListingPresenter::listing($listing, $request->user()->id)));
    }

    private function rules(bool $creating): array
    {
        $image = 'image|mimes:' . str_replace(['.', ' '], '', IMAGE_ACCEPTED_EXTENSIONS) . '|max:' . convertBytesToKiloBytes(maxUploadSize('image'));
        return [
            'type' => ['required', Rule::in(Listing::TYPES)],
            'title' => 'required|string|max:80',
            'description' => 'nullable|string|max:1000',
            'neighbourhood' => 'required|string|max:60',
            'address' => 'nullable|string|max:191',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'units_available' => 'required|integer|min:1|max:500',
            'furnished' => 'required|boolean',
            'rent' => 'required|numeric|min:1',
            'rent_period' => ['required', Rule::in(['month', 'year'])],
            'advance_months' => 'nullable|integer|min:0|max:24',
            'caution' => 'nullable|numeric|min:0',
            'available_from' => 'nullable|date',
            'water' => ['nullable', Rule::in(Listing::WATER)],
            'power' => ['nullable', Rule::in(Listing::POWER)],
            'backup_power' => 'required|boolean',
            'toilet' => ['nullable', Rule::in(['private', 'shared'])],
            'kitchen' => ['nullable', Rule::in(['private', 'shared', 'none'])],
            'fenced' => 'required|boolean',
            'security' => 'required|boolean',
            'amenities' => 'nullable|string|max:300',   // "wifi,cctv", keys of Listing::AMENITIES
            'owner_role' => ['required', Rule::in(['owner', 'caretaker'])],
            'accept_policy' => $creating ? 'accepted' : 'nullable',
            'photos' => $creating ? 'required|array|min:' . Listing::MIN_PHOTOS . '|max:' . Listing::MAX_PHOTOS : 'nullable|array|max:' . Listing::MAX_PHOTOS,
            'photos.*' => $image,
            'remove_photos' => 'nullable|array',
        ];
    }

    // ------------------------------------------------------------- owner actions

    public function confirm(Request $request, Listing $listing): JsonResponse
    {
        if (!$this->owns($request, $listing) || !in_array($listing->status, [Listing::LIVE, Listing::PAUSED], true)) {
            return $this->fail('listing', translate('Only a live or hidden listing can be confirmed.'));
        }
        if ($listing->status === Listing::PAUSED && $this->listings->cannotListReason($request->user())) {
            return $this->fail('owner', $this->listings->cannotListReason($request->user()));
        }
        return response()->json(responseFormatter(DEFAULT_UPDATE_200, ListingPresenter::listing($this->listings->confirm($listing)->load('photos'), $request->user()->id)));
    }

    public function rented(Request $request, Listing $listing): JsonResponse
    {
        if (!$this->owns($request, $listing) || !in_array($listing->status, [Listing::LIVE, Listing::PAUSED], true)) {
            return $this->fail('listing', translate('This listing is not open.'));
        }
        return response()->json(responseFormatter(DEFAULT_UPDATE_200, ListingPresenter::listing($this->listings->markRented($listing)->load('photos'), $request->user()->id)));
    }

    public function verification(Request $request, Listing $listing): JsonResponse
    {
        if (!$this->owns($request, $listing) || $listing->status !== Listing::LIVE) {
            return $this->fail('listing', translate('A listing must be live before E-Ride visits it.'));
        }
        $this->listings->requestVerification($listing);
        return response()->json(responseFormatter(DEFAULT_UPDATE_200, ListingPresenter::listing($listing->load('photos'), $request->user()->id)));
    }

    // ------------------------------------------------------------- reports and alerts

    public function report(Request $request, Listing $listing): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', Rule::in(ListingReport::REASONS)],
            'note' => 'nullable|string|max:500',
        ]);
        if ($listing->owner_id === $request->user()->id) {
            return $this->fail('listing', translate('This is your own listing.'));
        }
        if ($listing->reports()->where('reporter_id', $request->user()->id)->where('reason', $data['reason'])->where('status', ListingReport::OPEN)->exists()) {
            return $this->fail('report', translate('You already reported this place. E-Ride is looking at it.'));
        }
        $this->listings->report($listing, $request->user(), $data['reason'], $data['note'] ?? null);
        return response()->json(responseFormatter(DEFAULT_STORE_200));
    }

    public function alerts(Request $request): JsonResponse
    {
        return response()->json(responseFormatter(DEFAULT_200, ['items' => ListingAlert::where('user_id', $request->user()->id)->latest()->get()]));
    }

    public function storeAlert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['nullable', Rule::in(Listing::TYPES)],
            'neighbourhood' => 'nullable|string|max:60',
            'max_monthly_rent' => 'nullable|numeric|min:0',
            'verified_only' => 'nullable|boolean',
        ]);
        if (ListingAlert::where('user_id', $request->user()->id)->count() >= ListingAlert::MAX_PER_USER) {
            return $this->fail('alert', translate(key: 'You can keep {count} alerts. Remove one first.', replace: ['count' => ListingAlert::MAX_PER_USER]));
        }
        $alert = ListingAlert::create($data + ['user_id' => $request->user()->id, 'verified_only' => (bool)($data['verified_only'] ?? false)]);
        return response()->json(responseFormatter(DEFAULT_STORE_200, $alert));
    }

    public function destroyAlert(Request $request, int $alert): JsonResponse
    {
        ListingAlert::where('user_id', $request->user()->id)->whereKey($alert)->delete();
        return response()->json(responseFormatter(DEFAULT_DELETE_200));
    }

    // ------------------------------------------------------------- helpers

    private function owns(Request $request, Listing $listing): bool
    {
        return $listing->owner_id === $request->user()->id;
    }

    private function fail(string $code, string $message, int $status = 403): JsonResponse
    {
        return response()->json(responseFormatter(DEFAULT_400, errors: [['error_code' => $code, 'message' => $message]]), $status);
    }

    private function failValidation(ValidationException $e): JsonResponse
    {
        $errors = [];
        foreach ($e->errors() as $field => $messages) {
            $errors[] = ['error_code' => $field, 'message' => $messages[0]];
        }
        return response()->json(responseFormatter(DEFAULT_400, errors: $errors), 403);
    }
}
