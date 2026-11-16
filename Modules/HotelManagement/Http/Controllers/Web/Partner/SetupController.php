<?php

namespace Modules\HotelManagement\Http\Controllers\Web\Partner;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\HotelManagement\Entities\HotelRoom;
use Modules\HotelManagement\Entities\HotelRoomType;
use Modules\HotelManagement\Service\PartnerService;

/** The manager sets up once: room types (photos, beds, price from), room numbers, times and the reception phone. */
class SetupController extends Controller
{
    public function __construct(protected PartnerService $partners)
    {
    }

    public function index(Request $request): View
    {
        $hotel = $request->attributes->get('hotel');
        $types = HotelRoomType::where('hotel_id', $hotel->id)->where('active', true)
            ->with(['rooms' => fn($q) => $q->where('active', true)])->orderBy('sort')->get();
        $current = $request->query('type') === 'new' ? null : ($types->firstWhere('id', $request->query('type')) ?? $types->first());
        $checklist = PartnerService::checklist($hotel);
        return view('hotelmanagement::partner.setup', compact('types', 'current', 'checklist') + ['adding' => $request->query('type') === 'new' || $types->isEmpty()]);
    }

    public function saveType(Request $request, ?string $type = null): RedirectResponse
    {
        $hotel = $request->attributes->get('hotel');
        $model = $type ? HotelRoomType::where('hotel_id', $hotel->id)->where('active', true)->findOrFail($type) : null;
        try {
            $saved = $this->partners->saveType($hotel, $model, $request->all(), array_values(array_filter((array)$request->file('photos', []))));
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }
        return redirect()->route('hotel-partner.setup', ['type' => $saved->id])->with('toast', translate('Room type saved.'));
    }

    public function removeType(Request $request, string $type): RedirectResponse
    {
        $hotel = $request->attributes->get('hotel');
        try {
            $this->partners->removeType($hotel, HotelRoomType::where('hotel_id', $hotel->id)->findOrFail($type));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
        $this->partners->refreshStatus($hotel);
        return redirect()->route('hotel-partner.setup')->with('toast', translate('Room type removed.'));
    }

    public function addRooms(Request $request, string $type): RedirectResponse
    {
        $hotel = $request->attributes->get('hotel');
        $model = HotelRoomType::where('hotel_id', $hotel->id)->where('active', true)->findOrFail($type);
        try {
            $n = $this->partners->addRooms($hotel, $model, (string)$request->input('numbers'), $request->input('floor'));
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }
        return redirect()->route('hotel-partner.setup', ['type' => $model->id])->with('toast', translate(key: '{n} room(s) added.', replace: ['n' => $n]));
    }

    public function removeRoom(Request $request, string $room): RedirectResponse
    {
        $hotel = $request->attributes->get('hotel');
        $model = HotelRoom::where('hotel_id', $hotel->id)->where('active', true)->findOrFail($room);
        try {
            $this->partners->removeRoom($hotel, $model);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
        return redirect()->route('hotel-partner.setup', ['type' => $model->room_type_id])->with('toast', translate(key: 'Room {room} removed.', replace: ['room' => $model->number]));
    }

    public function saveTimes(Request $request): RedirectResponse
    {
        $hotel = $request->attributes->get('hotel');
        try {
            $this->partners->saveTimes($hotel, $request->all() + ['answer_always' => $request->boolean('answer_always') ? 1 : 0]);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }
        return back()->with('toast', translate('Saved.'));
    }
}
