<?php

namespace Modules\HotelManagement\Http\Controllers\Web\Admin;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\HotelManagement\Entities\Hotel;
use Modules\HotelManagement\Entities\HotelInquiry;
use Modules\HotelManagement\Entities\HotelRoom;
use Modules\HotelManagement\Entities\HotelStay;
use Modules\HotelManagement\Lib\HotelSettings;
use Modules\HotelManagement\Service\InquiryService;
use Modules\HotelManagement\Service\PartnerService;
use Modules\PlaceManagement\Entities\Place;

/** E-Ride admin: partner hotels (make one from a verified place to stay, watch how they do, pause), and the settings. */
class HotelAdminController extends Controller
{
    public function __construct(protected PartnerService $partners)
    {
    }

    public function index(): View
    {
        $settings = HotelSettings::all();
        $since = now()->subDays(30);
        $hotels = Hotel::with('place')->orderByRaw("status = 'live' desc")->orderBy('name')->get();
        $ids = $hotels->pluck('id');
        $rooms = HotelRoom::whereIn('hotel_id', $ids)->where('active', true)->selectRaw('hotel_id, count(*) n')->groupBy('hotel_id')->pluck('n', 'hotel_id');
        $asked = HotelInquiry::whereIn('hotel_id', $ids)->where('created_at', '>=', $since)->selectRaw('hotel_id, count(*) n')->groupBy('hotel_id')->pluck('n', 'hotel_id');
        $arrived = HotelStay::whereIn('hotel_id', $ids)->where('source', HotelStay::ERIDE)->whereNotNull('arrived_at')->where('arrived_at', '>=', $since)
            ->selectRaw('hotel_id, count(*) n')->groupBy('hotel_id')->pluck('n', 'hotel_id');
        // Waiting longer than the "slow" limit for a first answer, or a message.
        $slow = HotelInquiry::whereIn('hotel_id', $ids)->whereIn('status', HotelInquiry::ACTIVE)->where('unread_hotel', '>', 0)
            ->where('last_message_at', '<', now()->subHours($settings['slow_hours']))->selectRaw('hotel_id, count(*) n')->groupBy('hotel_id')->pluck('n', 'hotel_id');
        $rows = $hotels->map(fn(Hotel $h) => [
            'hotel' => $h,
            'rooms' => (int)($rooms[$h->id] ?? 0),
            'asked' => (int)($asked[$h->id] ?? 0),
            'arrived' => (int)($arrived[$h->id] ?? 0),
            'slow' => (int)($slow[$h->id] ?? 0),
            'answer' => InquiryService::answerMinutes($h),
            'fresh' => $h->isFresh(),
            'checklist' => PartnerService::checklist($h),
        ]);
        // Food & Shops stores that can be a hotel's restaurant (room service).
        $stores = \Modules\StoreManagement\Entities\Store::whereNull('parent_id')->where('is_active', 1)->orderBy('name')->get(['id', 'name']);
        $candidates = Place::where('explore_category', 'stay')->where('is_active', 1)->whereNotIn('id', Hotel::pluck('place_id'))->orderBy('name')->get(['id', 'name', 'address']);
        $totals = [
            'live' => $hotels->where('status', Hotel::LIVE)->count(),
            'asked' => array_sum($asked->all()),
            'arrived' => array_sum($arrived->all()),
        ];
        return view('hotelmanagement::admin.index', compact('settings', 'rows', 'candidates', 'totals', 'stores'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'place_id' => 'required|uuid',
            'manager_name' => 'required|string|max:80',
            'email' => 'nullable|email|max:120',
            'password' => 'required|string|min:8|max:100',
        ]);
        $place = Place::findOrFail($data['place_id']);
        try {
            [$hotel, $login] = $this->partners->makePartner($place, $data, auth()->id());
        } catch (ValidationException $e) {
            return back()->withInput($request->except('password'))->withErrors($e->errors());
        }
        return back()->with('hotel_login', [
            'hotel' => $hotel->name, 'login' => $login, 'url' => route('hotel-partner.login'),
        ]);
    }

    /** Room service: the hotel's own restaurant (a Food & Shops store), or none. */
    public function restaurant(Request $request, string $hotel): RedirectResponse
    {
        $model = Hotel::findOrFail($hotel);
        $storeId = $request->input('store_id') ?: null;
        if ($storeId) {
            \Modules\StoreManagement\Entities\Store::whereNull('parent_id')->findOrFail($storeId);
        }
        $model->forceFill(['restaurant_store_id' => $storeId])->save();
        Toastr::success($storeId ? translate('Restaurant linked: guests checked in can order room service.') : translate('Room service is off for this hotel.'));
        return back();
    }

    public function pause(Request $request, string $hotel): RedirectResponse
    {
        $model = Hotel::findOrFail($hotel);
        $pause = $request->boolean('pause');
        $this->partners->setPaused($model, $pause, $request->input('note'));
        Toastr::success($pause ? translate('Hotel paused: guests cannot ask it in the app.') : translate('Hotel resumed.'));
        return back();
    }

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'stale_hours' => 'required|integer|min:2|max:72',
            'slow_hours' => 'required|integer|min:1|max:72',
            'max_rooms' => 'required|integer|min:1|max:10',
            'max_nights' => 'required|integer|min:1|max:90',
            'days_ahead' => 'required|integer|min:7|max:365',
            'max_open' => 'required|integer|min:1|max:20',
        ]);
        $data['enabled'] = $request->boolean('enabled') ? 1 : 0;
        foreach ($data as $key => $value) {
            updateBusinessSetting(keyName: 'hotel_' . $key, settingType: HotelSettings::TYPE, value: (string)$value);
        }
        Toastr::success(translate('Hotel settings saved'));
        return back();
    }
}
