<?php

namespace Modules\HotelManagement\Http\Controllers\Api;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Modules\HotelManagement\Entities\Hotel;
use Modules\HotelManagement\Entities\HotelInquiry;
use Modules\HotelManagement\Entities\HotelMessage;
use Modules\HotelManagement\Lib\HotelSettings;
use Modules\HotelManagement\Service\InquiryService;
use Modules\HotelManagement\Transformers\HotelPresenter;
use Modules\PlaceManagement\Entities\Place;

/** Customer app: a partner hotel's rooms for some dates, and the guest's questions and chats. */
class HotelController extends Controller
{
    public function __construct(protected InquiryService $inquiries)
    {
    }

    /**
     * Adds the partner part (free rooms for the dates, answer time) to verified places to stay, partners
     * first. Called by the Explore list for "stay". Without dates: tonight.
     */
    public static function enrich(array $verified, Request $request): array
    {
        if (!HotelSettings::enabled() || !$verified) {
            return $verified;
        }
        [$from, $to] = self::dates($request);
        $hotels = Hotel::with('place')->where('status', Hotel::LIVE)->whereIn('place_id', array_column($verified, 'id'))->get()->keyBy(fn($h) => (string)$h->place_id);
        foreach ($verified as $i => $place) {
            $hotel = $hotels[(string)$place['id']] ?? null;
            $verified[$i]['partner'] = $hotel ? HotelPresenter::partner($hotel, $from, $to) : null;
        }
        usort($verified, fn($a, $b) => ($a['partner'] ? 0 : 1) <=> ($b['partner'] ? 0 : 1));
        return $verified;
    }

    /** check_in/check_out from the query, or tonight. Bad or past dates fall back to tonight. */
    public static function dates(Request $request): array
    {
        try {
            $from = $request->filled('check_in') ? Carbon::createFromFormat('Y-m-d', (string)$request->query('check_in'))->startOfDay() : today();
            $to = $request->filled('check_out') ? Carbon::createFromFormat('Y-m-d', (string)$request->query('check_out'))->startOfDay() : $from->copy()->addDay();
        } catch (\Throwable) {
            return [today(), today()->addDay()];
        }
        if ($from->lt(today()) || !$to->gt($from) || $from->diffInDays($to) > HotelSettings::get('max_nights')) {
            return [today(), today()->addDay()];
        }
        return [$from, $to];
    }

    /** A partner hotel by its id or its place's id, with room types and what is free for the dates. */
    public function show(Request $request, string $id): JsonResponse
    {
        $hotel = Hotel::with('place')->where('id', $id)->orWhere('place_id', $id)->first();
        if (!$hotel || !$hotel->place || !$hotel->place->is_active || !HotelSettings::enabled()) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }
        [$from, $to] = self::dates($request);
        $place = $hotel->place;
        $open = HotelInquiry::where('hotel_id', $hotel->id)->where('customer_id', $request->user()->id)->whereIn('status', HotelInquiry::ACTIVE)->latest()->value('id');
        return response()->json(responseFormatter(DEFAULT_200, [
            'id' => $hotel->id,
            'place_id' => $place->id,
            'name' => $hotel->name,
            'status' => $hotel->status,
            'category' => $place->category,
            'description' => $place->description,
            'address' => $place->address,
            'latitude' => $place->latitude,
            'longitude' => $place->longitude,
            'photos' => $place->photoUrls(),
            'amenities' => array_values(array_intersect(Place::AMENITIES, $place->amenities ?? [])),
            'check_in' => $from->toDateString(),
            'check_out' => $to->toDateString(),
            'partner' => HotelPresenter::partner($hotel, $from, $to),
            'room_types' => HotelPresenter::roomTypes($hotel, $from, $to),
            'open_inquiry_id' => $open,
            'limits' => ['max_rooms' => HotelSettings::get('max_rooms'), 'max_nights' => HotelSettings::get('max_nights'), 'days_ahead' => HotelSettings::get('days_ahead')],
        ]));
    }

    public function ask(Request $request, string $id): JsonResponse
    {
        $hotel = Hotel::with('place')->findOrFail($id);
        if (!HotelSettings::enabled()) {
            return response()->json(responseFormatter(DEFAULT_404), 404);
        }
        return $this->run(fn() => HotelPresenter::inquiry($this->inquiries->ask($request->user(), $hotel, $request->all()), true));
    }

    /** The guest's questions: going ones first, then the last 30 days. */
    public function inquiries(Request $request): JsonResponse
    {
        $list = HotelInquiry::with(['hotel.place', 'roomType'])->where('customer_id', $request->user()->id)
            ->where(fn($q) => $q->whereIn('status', HotelInquiry::ACTIVE)->orWhere('updated_at', '>=', now()->subDays(30)))
            ->orderByRaw("status = 'closed' asc")->orderByDesc('last_message_at')->limit(50)->get();
        return response()->json(responseFormatter(DEFAULT_200, [
            'inquiries' => $list->map(fn($q) => HotelPresenter::inquiry($q))->values()->all(),
            'unread' => (int)$list->whereIn('status', HotelInquiry::ACTIVE)->sum('unread_guest'),
        ]));
    }

    /** One question with its chat (new messages only with ?after=). Reading it marks it read. */
    public function inquiry(Request $request, string $id): JsonResponse
    {
        $q = $this->mine($request, $id);
        $this->inquiries->markReadByGuest($q);
        return response()->json(responseFormatter(DEFAULT_200, HotelPresenter::inquiry($q->fresh(['hotel.place', 'roomType']), true, (int)$request->query('after', 0) ?: null)));
    }

    public function send(Request $request, string $id): JsonResponse
    {
        $q = $this->mine($request, $id);
        return $this->run(function () use ($request, $q) {
            $message = $this->inquiries->guestWrite($q, $request->user(), (string)$request->input('body'));
            return ['message' => HotelPresenter::message($message)];
        });
    }

    /** "Let the room go" or "I no longer need it": closes the question, kept rooms are free again. */
    public function release(Request $request, string $id): JsonResponse
    {
        $q = $this->mine($request, $id);
        return $this->run(fn() => HotelPresenter::inquiry($this->inquiries->guestRelease($q, $request->user()), true));
    }

    protected function run(callable $fn): JsonResponse
    {
        try {
            return response()->json(responseFormatter(DEFAULT_200, $fn()));
        } catch (ValidationException $e) {
            $errors = [];
            foreach ($e->errors() as $field => $messages) {
                $errors[] = ['error_code' => $field, 'message' => $messages[0]];
            }
            return response()->json(responseFormatter(DEFAULT_400, errors: $errors), 403);
        }
    }

    protected function mine(Request $request, string $id): HotelInquiry
    {
        return HotelInquiry::with(['hotel.place', 'roomType'])->where('customer_id', $request->user()->id)->findOrFail($id);
    }
}
