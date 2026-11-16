<?php

namespace Modules\HotelManagement\Http\Controllers\Web\Partner;

use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\HotelManagement\Entities\HotelRoom;
use Modules\HotelManagement\Entities\HotelStay;
use Modules\HotelManagement\Service\StayService;

/** The coming nights, room by room: phone, WhatsApp and walk-in bookings go here so the app never shows a room that is gone. */
class NightsController extends Controller
{
    public const DAYS = 14;

    public function __construct(protected StayService $stays)
    {
    }

    public function index(Request $request): View
    {
        $hotel = $request->attributes->get('hotel');
        $from = $request->filled('from') && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$request->query('from'))
            ? Carbon::parse($request->query('from'))->max(today()) : today();
        $calendar = $this->stays->calendar($hotel, $from, self::DAYS);
        $types = $hotel->roomTypes()->where('active', true)->get();
        return view('hotelmanagement::partner.nights', compact('calendar', 'types', 'from') + ['days' => self::DAYS]);
    }

    public function book(Request $request): RedirectResponse
    {
        $hotel = $request->attributes->get('hotel');
        $data = $request->validate([
            'room_id' => 'required|string',
            'check_in' => 'required|date_format:Y-m-d',
            'check_out' => 'required|date_format:Y-m-d',
            'source' => 'required|string|max:10',
            'guest_name' => 'nullable|string|max:80',
            'guests' => 'nullable|integer|min:1|max:20',
        ]);
        $room = HotelRoom::where('hotel_id', $hotel->id)->where('active', true)->findOrFail($data['room_id']);
        try {
            $this->stays->book($hotel, $room, Carbon::parse($data['check_in']), Carbon::parse($data['check_out']), $data['source'], $data, auth()->id());
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }
        $nights = Carbon::parse($data['check_in'])->diffInDays(Carbon::parse($data['check_out']));
        return back()->with('toast', $data['source'] === HotelStay::BLOCKED
            ? translate(key: 'Room {room} blocked for {n} night(s).', replace: ['room' => $room->number, 'n' => $nights])
            : translate(key: 'Room {room} booked for {n} night(s).', replace: ['room' => $room->number, 'n' => $nights]));
    }
}
