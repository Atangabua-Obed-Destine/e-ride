<?php

namespace Modules\MarketManagement\Http\Controllers\Api;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\MarketManagement\Entities\MarketBooking;
use Modules\MarketManagement\Entities\MarketItem;
use Modules\MarketManagement\Entities\MarketReview;
use Modules\MarketManagement\Lib\MarketSettings;
use Modules\MarketManagement\Service\BookingService;
use Modules\MarketManagement\Transformers\MarketPresenter;

/** Customer app, E-Ride Market services: free times, booking, quotes, and every step after, for both sides. */
class MarketBookingController extends Controller
{
    public function __construct(protected BookingService $bookings)
    {
    }

    /** ?date=Y-m-d: the next days (free counts) and the start times of one day. */
    public function slots(Request $request, string $code): JsonResponse
    {
        $request->validate(['date' => 'nullable|date_format:Y-m-d', 'ignore' => 'nullable|string|max:40']);
        $item = $this->service($code);
        if ($item instanceof JsonResponse) {
            return $item;
        }
        $days = $this->bookings->days($item);
        $date = $request->input('date') ?: (collect($days)->firstWhere('free', '>', 0)['date'] ?? now()->format('Y-m-d'));
        return response()->json(responseFormatter(DEFAULT_200, [
            'days' => $days,
            'date' => $date,
            'slots' => $this->bookings->slots($item, Carbon::parse($date)->startOfDay(), $request->input('ignore')),
            'duration_minutes' => (int)($item->duration_minutes ?: 60),
        ]));
    }

    public function book(Request $request, string $code): JsonResponse
    {
        $request->validate([
            'starts_at' => 'required|date_format:Y-m-d H:i',
            'extras' => 'nullable',
            'note' => 'nullable|string|max:600',
            'location' => ['nullable', Rule::in(MarketItem::SERVICE_WHERE)],
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'address' => 'nullable|string|max:191',
        ]);
        $item = $this->service($code);
        if ($item instanceof JsonResponse) {
            return $item;
        }
        $extras = $request->input('extras');
        $extras = is_array($extras) ? $extras : (json_decode((string)$extras, true) ?: array_filter(explode(',', (string)$extras)));
        $booking = $this->bookings->book($request->user(), $item, ['extras' => $extras] + $request->only(['starts_at', 'note', 'location', 'lat', 'lng', 'address']));
        return $this->ok($request, $booking);
    }

    public function quote(Request $request, string $code): JsonResponse
    {
        $image = 'image|max:' . convertBytesToKiloBytes(maxUploadSize('image'));
        $request->validate([
            'note' => 'required|string|max:600',
            'when_text' => 'nullable|string|max:80',
            'location' => ['nullable', Rule::in(MarketItem::SERVICE_WHERE)],
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'address' => 'nullable|string|max:191',
            'photos' => 'nullable|array|max:' . MarketBooking::MAX_PHOTOS,
            'photos.*' => $image,
        ]);
        $item = $this->service($code);
        if ($item instanceof JsonResponse) {
            return $item;
        }
        $booking = $this->bookings->requestQuote($request->user(), $item, $request->only(['note', 'when_text', 'location', 'lat', 'lng', 'address']),
            $request->file('photos', []));
        return $this->ok($request, $booking);
    }

    /** ?role=client|provider&tab=upcoming|past|requests|day&date=Y-m-d */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'role' => 'nullable|in:client,provider',
            'tab' => 'nullable|in:upcoming,past,requests,day',
            'date' => 'nullable|date_format:Y-m-d',
        ]);
        $user = $request->user();
        $role = $request->input('role', 'client');
        $list = $this->bookings->listFor($user, $role, $request->input('tab', 'upcoming'), $request->input('date'));
        return response()->json(responseFormatter(DEFAULT_200, [
            'bookings' => $list->map(fn(MarketBooking $b) => MarketPresenter::booking($b, $user->id))->values()->all(),
            'counts' => $this->bookings->counts($user),
            'days' => $role === 'provider' ? $this->bookings->dayCounts($user) : null,
        ]));
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $booking = $this->mine($request, $id);
        return $booking instanceof JsonResponse ? $booking : $this->ok($request, $booking);
    }

    public function accept(Request $request, string $id): JsonResponse
    {
        return $this->act($request, $id, fn($b) => $this->bookings->accept($request->user(), $b));
    }

    public function decline(Request $request, string $id): JsonResponse
    {
        $request->validate(['reason' => 'nullable|string|max:300']);
        return $this->act($request, $id, fn($b) => $this->bookings->decline($request->user(), $b, $request->input('reason')));
    }

    public function suggest(Request $request, string $id): JsonResponse
    {
        $request->validate(['starts_at' => 'required|date_format:Y-m-d H:i']);
        return $this->act($request, $id, fn($b) => $this->bookings->suggest($request->user(), $b, $request->input('starts_at')));
    }

    public function sendQuote(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'price' => 'required|numeric|min:1|max:100000000',
            'starts_at' => 'required|date_format:Y-m-d H:i',
            'duration_minutes' => 'nullable|integer|min:15|max:1440',
            'note' => 'nullable|string|max:400',
        ]);
        return $this->act($request, $id, fn($b) => $this->bookings->sendQuote($request->user(), $b, $request->only(['price', 'starts_at', 'duration_minutes', 'note'])));
    }

    /** The client accepts a suggested time or a price. */
    public function agree(Request $request, string $id): JsonResponse
    {
        return $this->act($request, $id, fn($b) => $b->status === MarketBooking::QUOTED
            ? $this->bookings->acceptQuote($request->user(), $b)
            : $this->bookings->acceptSuggestion($request->user(), $b));
    }

    public function refuse(Request $request, string $id): JsonResponse
    {
        return $this->act($request, $id, fn($b) => $this->bookings->refuse($request->user(), $b));
    }

    public function reschedule(Request $request, string $id): JsonResponse
    {
        $request->validate(['starts_at' => 'required|date_format:Y-m-d H:i']);
        return $this->act($request, $id, fn($b) => $this->bookings->reschedule($request->user(), $b, $request->input('starts_at')));
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $request->validate(['reason' => 'nullable|string|max:300']);
        return $this->act($request, $id, fn($b) => $this->bookings->cancel($request->user(), $b, $request->input('reason')));
    }

    public function complete(Request $request, string $id): JsonResponse
    {
        $request->validate(['code' => 'required|string|size:4', 'final_price' => 'nullable|numeric|min:0|max:100000000']);
        return $this->act($request, $id, fn($b) => $this->bookings->complete($request->user(), $b, (string)$request->input('code'),
            $request->filled('final_price') ? (float)$request->input('final_price') : null));
    }

    public function noShow(Request $request, string $id): JsonResponse
    {
        return $this->act($request, $id, fn($b) => $this->bookings->noShow($request->user(), $b));
    }

    public function review(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'stars' => 'required|integer|min:1|max:5',
            'tags' => 'nullable',
            'comment' => 'nullable|string|max:500',
        ]);
        $booking = $this->mine($request, $id);
        if ($booking instanceof JsonResponse) {
            return $booking;
        }
        $tags = $request->input('tags');
        $tags = is_array($tags) ? $tags : array_filter(explode(',', (string)$tags));
        $this->bookings->review($request->user(), $booking, (int)$request->input('stars'), array_intersect(MarketReview::SERVICE_TAGS, $tags),
            $request->input('comment'));
        return $this->ok($request, $booking->fresh(['item.photos', 'shop', 'client', 'review']));
    }

    // ------------------------------------------------------------------

    private function act(Request $request, string $id, callable $fn): JsonResponse
    {
        $booking = $this->mine($request, $id);
        if ($booking instanceof JsonResponse) {
            return $booking;
        }
        return $this->ok($request, $fn($booking));
    }

    private function service(string $code): MarketItem|JsonResponse
    {
        if (!MarketSettings::enabled()) {
            return $this->fail('market', translate('E-Ride Market is not available right now.'));
        }
        $item = MarketItem::with('shop')->where('code', $code)->first();
        if (!$item || !$item->isService() || !$item->isPublic()) {
            return $this->fail('item', translate('This service is not available.'), 404);
        }
        return $item;
    }

    private function mine(Request $request, string $id): MarketBooking|JsonResponse
    {
        $booking = MarketBooking::with(['item.photos', 'shop', 'client', 'provider'])->find($id);
        if (!$booking || !$booking->roleOf($request->user()->id)) {
            return $this->fail('booking', translate('This booking is not available.'), 404);
        }
        return $booking;
    }

    private function ok(Request $request, MarketBooking $booking): JsonResponse
    {
        return response()->json(responseFormatter(DEFAULT_200, MarketPresenter::booking($booking, $request->user()->id, true)));
    }

    private function fail(string $code, string $message, int $status = 403): JsonResponse
    {
        return response()->json(responseFormatter(DEFAULT_400, errors: [['error_code' => $code, 'message' => $message]]), $status);
    }
}
