<?php

namespace Modules\TripManagement\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Modules\TripManagement\Entities\TripRequest;
use Modules\TripManagement\Lib\HealthRideSettings;
use Modules\TripManagement\Service\HealthRideService;

/** Trip management > Health rides: every health ride, how fast drivers took them, and the settings. */
class HealthRideAdminController extends Controller
{
    public const PERIODS = ['today' => 'Today', 'week' => 'Last 7 days', 'month' => 'Last 30 days', 'all' => 'All'];

    public function index(Request $request, HealthRideService $health): View
    {
        $this->authorize('trip_view');
        $period = array_key_exists($request->query('period'), self::PERIODS) ? $request->query('period') : 'week';
        $since = match ($period) {
            'today' => now()->startOfDay(),
            'week' => now()->subDays(7),
            'month' => now()->subDays(30),
            default => null,
        };
        $base = TripRequest::where('is_health_ride', 1)->when($since, fn($q) => $q->where('created_at', '>=', $since));
        $trips = (clone $base)->with([
            'customer:id,first_name,last_name,phone',
            'driver:id,first_name,last_name,phone',
            'coordinate:id,trip_request_id,pickup_address,destination_address',
            'tripStatus:id,trip_request_id,accepted,completed,cancelled',
        ])->latest()->paginate(paginationLimit())->withQueryString();

        // How long until a driver accepted: the number that tells if health rides work.
        $accepted = (clone $base)->with('tripStatus:id,trip_request_id,accepted')->get(['id', 'created_at'])
            ->map(fn($t) => $t->tripStatus?->accepted ? Carbon::parse($t->tripStatus->accepted)->diffInSeconds($t->created_at, true) / 60 : null);
        $waits = $accepted->filter(fn($m) => $m !== null)->sort()->values();
        $stats = [
            'total' => $accepted->count(),
            'accepted' => $waits->count(),
            'median_wait' => $waits->count() ? round($waits[intdiv($waits->count(), 2)], 1) : null,
            'completed' => (clone $base)->where('current_status', COMPLETED)->count(),
            'cancelled' => (clone $base)->where('current_status', CANCELLED)->count(),
        ];
        $settings = HealthRideSettings::all();
        $radiusKm = $health->radiusKm();
        $normalRadius = (float)(get_cache('search_radius') ?? 5);
        return view('tripmanagement::admin.health-ride.index', compact('trips', 'stats', 'period', 'settings', 'radiusKm', 'normalRadius'));
    }

    public function settings(Request $request): RedirectResponse
    {
        $this->authorize('trip_edit');
        $data = $request->validate([
            'health_rides_enabled' => 'nullable|boolean',
            'health_rides_deposit' => 'nullable|boolean',
            'health_rides_daily_limit' => 'required|integer|min:1|max:10',
            'health_rides_radius_km' => 'nullable|numeric|min:0|max:500',
            'health_rides_emergency_numbers' => 'nullable|string|max:600',
        ]);
        $data['health_rides_enabled'] = $request->boolean('health_rides_enabled') ? 1 : 0;
        $data['health_rides_deposit'] = $request->boolean('health_rides_deposit') ? 1 : 0;
        $data['health_rides_radius_km'] = (float)($data['health_rides_radius_km'] ?? 0);
        $data['health_rides_emergency_numbers'] = trim((string)($data['health_rides_emergency_numbers'] ?? ''));
        foreach ($data as $key => $value) {
            updateBusinessSetting(keyName: $key, settingType: HealthRideSettings::TYPE, value: (string)$value);
        }
        Cache::forget(CACHE_BUSINESS_SETTINGS);
        Toastr::success(translate('Health ride settings saved'));
        return back();
    }
}
