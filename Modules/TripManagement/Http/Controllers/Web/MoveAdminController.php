<?php

namespace Modules\TripManagement\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\FareManagement\Entities\TripFare;
use Modules\FareManagement\Entities\ZoneWiseDefaultTripFare;
use Modules\TripManagement\Entities\MoveDirectoryListing;
use Modules\TripManagement\Entities\TripRequest;
use Modules\TripManagement\Lib\MoveSettings;
use Modules\UserManagement\Entities\User;
use Modules\VehicleManagement\Entities\VehicleCategory;
use Modules\ZoneManagement\Entities\Zone;

/** E-Ride Move: the moves, settings, deposits per zone and size, and the truck owner directory. */
class MoveAdminController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('trip_view');
        $status = in_array($request->query('status'), [PENDING, ACCEPTED, OUT_FOR_PICKUP, ONGOING, COMPLETED, CANCELLED]) ? $request->query('status') : null;
        $search = trim((string)$request->query('search', ''));
        $moves = TripRequest::with(['customer:id,first_name,last_name,phone', 'driver:id,first_name,last_name,phone', 'vehicleCategory:id,name,type', 'deposit', 'coordinate'])
            ->where('is_move', true)
            ->when($status, fn($q) => $q->where('current_status', $status))
            ->when($search !== '', fn($q) => $q->where('ref_id', 'like', '%' . $search . '%'))
            ->latest()
            ->paginate(paginationLimit())
            ->withQueryString();
        $since = now()->startOfMonth();
        $stats = [
            'requested' => TripRequest::where('is_move', true)->where('created_at', '>=', $since)->count(),
            'completed' => TripRequest::where('is_move', true)->where('current_status', COMPLETED)->where('created_at', '>=', $since)->count(),
            'fees' => (float)DB::table('transactions')->where('attribute', 'move_booking_fee')->where('created_at', '>=', $since)->sum('credit'),
            'contacts' => DB::table('move_directory_contacts')->where('created_at', '>=', $since)->count(),
        ];
        return view('tripmanagement::admin.move.index', compact('moves', 'status', 'search', 'stats'));
    }

    // -------------------------------------------------------------- settings

    public function settings(): View
    {
        $this->authorize('trip_view');
        $settings = MoveSettings::all();
        return view('tripmanagement::admin.move.settings', compact('settings'));
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $this->authorize('trip_edit');
        $data = $request->validate([
            'deposit_window_minutes' => 'required|integer|min:1|max:30',
            'deposit_free_cancel_seconds' => 'required|integer|min:0|max:900',
            'deposit_driver_share_percent' => 'required|numeric|min:0|max:100',
        ]);
        $save = fn(string $key, $value) => updateBusinessSetting(keyName: $key, settingType: TRIP_SETTINGS, value: (string)$value);
        $save('move_status', $request->boolean('status') ? '1' : '0');
        $save('move_deposit_status', $request->boolean('deposit_status') ? '1' : '0');
        $save('move_directory_status', $request->boolean('directory_status') ? '1' : '0');
        $save('move_deposit_window_minutes', $data['deposit_window_minutes']);
        $save('move_deposit_free_cancel_seconds', $data['deposit_free_cancel_seconds']);
        $save('move_deposit_driver_share_percent', $data['deposit_driver_share_percent']);
        Cache::forget(CACHE_BUSINESS_SETTINGS);
        Toastr::success(translate('E-Ride Move settings saved'));
        return back();
    }

    // -------------------------------------------------------------- deposits

    public function deposits(Request $request): View
    {
        $this->authorize('fare_view');
        $zones = Zone::where('is_active', 1)->orderBy('name')->get(['id', 'name']);
        $zone = $zones->firstWhere('id', $request->query('zone_id')) ?? $zones->first();
        $categories = VehicleCategory::move()->orderBy('name')->get();
        $amounts = $zone ? TripFare::where('zone_id', $zone->id)->whereIn('vehicle_category_id', $categories->pluck('id'))->pluck('booking_deposit', 'vehicle_category_id') : collect();
        return view('tripmanagement::admin.move.deposits', compact('zones', 'zone', 'categories', 'amounts'));
    }

    /**
     * The move sizes offered in a zone and their deposits. A move vehicle is
     * offered where it has a fare row; the row holds only the deposit (E-Ride
     * sets no price for moves).
     */
    public function updateDeposits(Request $request): RedirectResponse
    {
        $this->authorize('fare_add');
        $data = $request->validate([
            'zone_id' => 'required|exists:zones,id',
            'offered' => 'array',
            'deposit' => 'array',
            'deposit.*' => 'nullable|numeric|min:0|max:1000000',
        ]);
        $categories = VehicleCategory::move()->get();
        DB::transaction(function () use ($data, $categories) {
            $default = ZoneWiseDefaultTripFare::firstOrCreate(['zone_id' => $data['zone_id']], [
                'base_fare' => 0, 'base_fare_per_km' => 0, 'waiting_fee_per_min' => 0, 'cancellation_fee_percent' => 0,
                'min_cancellation_fee' => 0, 'idle_fee_per_min' => 0, 'trip_delay_fee_per_min' => 0,
                'penalty_fee_for_cancel' => 0, 'fee_add_to_next' => 0, 'booking_deposit' => 0, 'category_wise_different_fare' => 1,
            ]);
            foreach ($categories as $category) {
                $offered = !empty($data['offered'][$category->id]);
                $existing = TripFare::where('zone_id', $data['zone_id'])->where('vehicle_category_id', $category->id)->first();
                if (!$offered) {
                    $existing?->delete();
                    continue;
                }
                $values = [
                    'zone_wise_default_trip_fare_id' => $default->id,
                    'base_fare' => 0, 'base_fare_per_km' => 0, 'waiting_fee_per_min' => 0, 'cancellation_fee_percent' => 0,
                    'min_cancellation_fee' => 0, 'idle_fee_per_min' => 0, 'trip_delay_fee_per_min' => 0,
                    'penalty_fee_for_cancel' => 0, 'fee_add_to_next' => 0,
                    'booking_deposit' => round((float)($data['deposit'][$category->id] ?? 0), 2),
                ];
                $existing ? $existing->update($values) : TripFare::create($values + ['zone_id' => $data['zone_id'], 'vehicle_category_id' => $category->id]);
            }
        });
        Toastr::success(translate('Move deposits saved'));
        return redirect()->route('admin.move.deposits', ['zone_id' => $data['zone_id']]);
    }

    // ------------------------------------------------------------- directory

    public function directory(Request $request): View
    {
        $this->authorize('trip_view');
        $search = trim((string)$request->query('search', ''));
        $listings = MoveDirectoryListing::with(['zone:id,name', 'vehicleCategory:id,name,type', 'user:id,first_name,last_name'])
            ->withCount('contacts')
            ->when($search !== '', fn($q) => $q->where(fn($w) => $w->where('owner_name', 'like', '%' . $search . '%')->orWhere('phone', 'like', '%' . $search . '%')))
            ->latest()
            ->paginate(paginationLimit())
            ->withQueryString();
        return view('tripmanagement::admin.move.directory', compact('listings', 'search'));
    }

    public function createListing(): View
    {
        $this->authorize('trip_edit');
        return view('tripmanagement::admin.move.listing-form', ['listing' => new MoveDirectoryListing(['is_active' => true])] + $this->formData());
    }

    public function editListing(string $id): View
    {
        $this->authorize('trip_edit');
        return view('tripmanagement::admin.move.listing-form', ['listing' => MoveDirectoryListing::findOrFail($id)] + $this->formData());
    }

    public function storeListing(Request $request): RedirectResponse
    {
        $this->authorize('trip_edit');
        $listing = new MoveDirectoryListing();
        $this->saveListing($request, $listing);
        Toastr::success(translate('Listing added'));
        return redirect()->route('admin.move.directory');
    }

    public function updateListing(Request $request, string $id): RedirectResponse
    {
        $this->authorize('trip_edit');
        $this->saveListing($request, MoveDirectoryListing::findOrFail($id));
        Toastr::success(translate('Listing saved'));
        return redirect()->route('admin.move.directory');
    }

    public function toggleListing(string $id): RedirectResponse
    {
        $this->authorize('trip_edit');
        $listing = MoveDirectoryListing::findOrFail($id);
        $listing->is_active = !$listing->is_active;
        $listing->save();
        Toastr::success($listing->is_active ? translate('Listing shown to customers') : translate('Listing hidden'));
        return back();
    }

    protected function saveListing(Request $request, MoveDirectoryListing $listing): void
    {
        $data = $request->validate([
            'zone_id' => 'required|exists:zones,id',
            'vehicle_category_id' => ['required', Rule::exists('vehicle_categories', 'id')->whereIn('type', MOVE_VEHICLE_TYPES)],
            'owner_name' => 'required|string|max:120',
            'phone' => 'required|string|max:30',
            'whatsapp' => 'nullable|string|max:30',
            'areas' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:500',
            'driver_phone' => 'nullable|string|max:30',
            'photo' => 'nullable|image|max:' . convertBytesToKiloBytes(maxUploadSize('image')),
            'consent' => 'accepted',
        ]);
        $driverId = null;
        if (!empty($data['driver_phone'])) {
            $driverId = User::where('user_type', DRIVER)->where('phone', $data['driver_phone'])->value('id');
        }
        $listing->fill([
            'zone_id' => $data['zone_id'],
            'vehicle_category_id' => $data['vehicle_category_id'],
            'user_id' => $driverId,
            'owner_name' => $data['owner_name'],
            'phone' => $data['phone'],
            'whatsapp' => $data['whatsapp'] ?? null,
            'areas' => $data['areas'] ?? null,
            'note' => $data['note'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);
        $listing->consent_at ??= now();
        if ($request->hasFile('photo')) {
            $listing->photo = fileUploader(MoveDirectoryListing::PHOTO_FOLDER, APPLICATION_IMAGE_FORMAT, $request->file('photo'), $listing->photo);
        }
        $listing->save();
    }

    protected function formData(): array
    {
        return [
            'zones' => Zone::where('is_active', 1)->orderBy('name')->get(['id', 'name']),
            'categories' => VehicleCategory::move()->orderBy('name')->get(['id', 'name', 'type']),
        ];
    }
}
