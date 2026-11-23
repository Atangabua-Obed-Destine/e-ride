<?php

namespace Modules\HotelManagement\Http\Controllers\Web\Partner;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\HotelManagement\Entities\HotelRoom;
use Modules\HotelManagement\Entities\HotelRoomCode;
use Modules\HotelManagement\Service\RoomService;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/** Setup › Room service (manager): the hotel's restaurant on E-Ride, and the QR cards for the rooms. */
class RoomServiceController extends Controller
{
    public function __construct(protected RoomService $rooms)
    {
    }

    public function index(Request $request): View
    {
        $hotel = $request->attributes->get('hotel');
        $codes = HotelRoomCode::where('hotel_id', $hotel->id)->whereNull('revoked_at')->get()
            ->sortBy(fn($c) => str_pad($c->room_label, 20, '0', STR_PAD_LEFT))->values();
        return view('hotelmanagement::partner.room-service', [
            'restaurant' => $this->rooms->restaurant($hotel),
            'codes' => $codes,
            // On the E-Ride rooms board the numbers are known; on POS Innova the manager types them.
            'suggested' => $hotel->usesPos() ? '' : HotelRoom::where('hotel_id', $hotel->id)->where('active', true)->orderByRaw('LENGTH(number)')->orderBy('number')->pluck('number')->implode(', '),
        ]);
    }

    public function make(Request $request): RedirectResponse
    {
        $hotel = $request->attributes->get('hotel');
        $labels = RoomService::parseRooms((string)$request->input('rooms', ''));
        if (!$labels) {
            return back()->withErrors(['rooms' => translate('Write the room numbers, for example 101-110, 201, 202.')]);
        }
        $n = $this->rooms->codes($hotel, $labels)->count();
        return back()->with('success', translate(key: '{n} room cards ready. Print them below.', replace: ['n' => $n]));
    }

    public function reissue(Request $request, string $code): RedirectResponse
    {
        $row = HotelRoomCode::where('hotel_id', $request->attributes->get('hotel')->id)->whereNull('revoked_at')->findOrFail($code);
        $this->rooms->reissue($row);
        return back()->with('success', translate('New card made. The old card of this room no longer works: print the new one.'));
    }

    /** The cards to print, 6 per page. */
    public function print(Request $request): View
    {
        $hotel = $request->attributes->get('hotel');
        $codes = HotelRoomCode::where('hotel_id', $hotel->id)->whereNull('revoked_at')->get()
            ->sortBy(fn($c) => str_pad($c->room_label, 20, '0', STR_PAD_LEFT))->values()
            ->map(fn(HotelRoomCode $c) => ['room' => $c->room_label, 'token' => $c->token,
                'qr' => (string)QrCode::format('svg')->size(180)->margin(0)->errorCorrection('M')->generate($c->url())]);
        return view('hotelmanagement::partner.room-cards', ['codes' => $codes, 'restaurant' => $this->rooms->restaurant($hotel)]);
    }
}
