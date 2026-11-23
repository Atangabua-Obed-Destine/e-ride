<?php

namespace Modules\HotelManagement\Http\Controllers\Web\Partner;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\HotelManagement\Entities\HotelPosLink;
use Modules\HotelManagement\Entities\HotelPosType;
use Modules\HotelManagement\Entities\HotelRoomType;
use Modules\HotelManagement\Service\HotelPosService;
use Modules\HotelManagement\Service\PartnerService;

/** Setup › POS Innova (manager): the hotel's key for its POS, and POS room types mapped to E-Ride ones. */
class PosController extends Controller
{
    public function __construct(protected HotelPosService $pos, protected PartnerService $partners)
    {
    }

    protected function link(Request $request): ?HotelPosLink
    {
        return HotelPosLink::where('hotel_id', $request->attributes->get('hotel')->id)->whereNull('revoked_at')->latest()->first();
    }

    public function index(Request $request): View
    {
        $hotel = $request->attributes->get('hotel');
        $link = $this->link($request);
        return view('hotelmanagement::partner.pos', [
            'link' => $link,
            'posTypes' => $link ? $this->pos->types($link) : collect(),
            'roomTypes' => HotelRoomType::where('hotel_id', $hotel->id)->where('active', true)->orderBy('sort')->get(['id', 'name']),
            'apiUrl' => url('api/channel/posinnova/v1/hotel'),
            'newKey' => session('pos_key'),
        ]);
    }

    public function createLink(Request $request): RedirectResponse
    {
        [, $key] = $this->pos->createLink($request->attributes->get('hotel'), auth()->id());
        return redirect()->route('hotel-partner.pos')->with('pos_key', $key);
    }

    public function revoke(Request $request): RedirectResponse
    {
        if ($link = $this->link($request)) {
            $this->pos->revoke($link);
        }
        $this->partners->refreshStatus($request->attributes->get('hotel')->fresh());
        return redirect()->route('hotel-partner.pos')->with('success', translate('POS Innova is unlinked. Your rooms board works from E-Ride again.'));
    }

    protected function posType(Request $request, string $type): array
    {
        $link = $this->link($request);
        abort_unless($link, 404);
        return [$link, HotelPosType::where('link_id', $link->id)->findOrFail($type)];
    }

    public function map(Request $request, string $type): RedirectResponse
    {
        [$link, $t] = $this->posType($request, $type);
        $this->pos->mapType($link, $t, $request->input('room_type_id') ?: null);
        $this->partners->refreshStatus($request->attributes->get('hotel')->fresh());
        return back()->with('success', $request->input('room_type_id') ? translate('Linked. Guests now see this room type with the free rooms from POS.') : translate('Unlinked.'));
    }

    public function createType(Request $request, string $type): RedirectResponse
    {
        [$link, $t] = $this->posType($request, $type);
        $room = $this->pos->createRoomType($link, $t);
        $this->partners->refreshStatus($request->attributes->get('hotel')->fresh());
        return redirect()->route('hotel-partner.setup', ['type' => $room->id])
            ->with('success', translate('Room type added from POS. Add its photos and amenities here.'));
    }
}
