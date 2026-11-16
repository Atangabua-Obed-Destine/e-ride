<?php

namespace Modules\HotelManagement\Http\Controllers\Web\Partner;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\HotelManagement\Entities\HotelInquiry;
use Modules\HotelManagement\Entities\HotelRoom;
use Modules\HotelManagement\Entities\HotelStay;
use Modules\HotelManagement\Service\StayService;

/** The rooms board: what every room is now, and the desk's actions on it. */
class RoomsController extends Controller
{
    public function __construct(protected StayService $stays)
    {
    }

    public function index(Request $request): View
    {
        $hotel = $request->attributes->get('hotel');
        $board = $this->stays->board($hotel);
        $counts = array_count_values(array_map(fn($r) => $r['status'], $board['rooms']));
        return view('hotelmanagement::partner.rooms', compact('board', 'counts'));
    }

    /** For the menu badge and ring: questions waiting for the reception, and the newest one. */
    public function feed(Request $request): JsonResponse
    {
        $hotel = $request->attributes->get('hotel');
        $waiting = HotelInquiry::where('hotel_id', $hotel->id)->whereIn('status', HotelInquiry::ACTIVE)->where('unread_hotel', '>', 0);
        return response()->json([
            'count' => (clone $waiting)->count(),
            'latest' => (clone $waiting)->max('last_message_at'),
        ]);
    }

    public function state(Request $request, string $room): RedirectResponse
    {
        $hotel = $request->attributes->get('hotel');
        return $this->act(fn() => $this->stays->setRoomState($hotel, $this->room($hotel, $room), (string)$request->input('state'), $request->input('note')),
            translate('Room updated. Guests see it at once.'));
    }

    public function walkIn(Request $request, string $room): RedirectResponse
    {
        $hotel = $request->attributes->get('hotel');
        $data = $request->validate([
            'nights' => 'required|integer|min:1|max:60',
            'guests' => 'nullable|integer|min:1|max:20',
            'guest_name' => 'nullable|string|max:80',
        ]);
        return $this->act(fn() => $this->stays->walkIn($hotel, $this->room($hotel, $room), today()->addDays((int)$data['nights']), $data, auth()->id()),
            translate('Walk-in guest in. The room is occupied.'));
    }

    public function arrive(Request $request, string $stay): RedirectResponse
    {
        $hotel = $request->attributes->get('hotel');
        return $this->act(fn() => $this->stays->arrive($hotel, $this->stay($hotel, $stay)), translate('Welcome. The room is occupied.'));
    }

    public function noShow(Request $request, string $stay): RedirectResponse
    {
        $hotel = $request->attributes->get('hotel');
        return $this->act(fn() => $this->stays->noShow($hotel, $this->stay($hotel, $stay)), translate('The room is free again.'));
    }

    public function leave(Request $request, string $stay): RedirectResponse
    {
        $hotel = $request->attributes->get('hotel');
        return $this->act(fn() => $this->stays->leave($hotel, $this->stay($hotel, $stay)), translate('Guest left. The room is waiting for cleaning.'));
    }

    public function cancel(Request $request, string $stay): RedirectResponse
    {
        $hotel = $request->attributes->get('hotel');
        return $this->act(fn() => $this->stays->cancel($hotel, $this->stay($hotel, $stay)), translate('Booking removed. Those nights are free again.'));
    }

    protected function act(callable $fn, string $done): RedirectResponse
    {
        try {
            $fn();
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
        return back()->with('toast', $done);
    }

    protected function room($hotel, string $id): HotelRoom
    {
        return HotelRoom::where('hotel_id', $hotel->id)->where('active', true)->findOrFail($id);
    }

    protected function stay($hotel, string $id): HotelStay
    {
        return HotelStay::where('hotel_id', $hotel->id)->findOrFail($id);
    }
}
