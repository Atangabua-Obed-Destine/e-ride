<?php

namespace Modules\TripManagement\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Modules\TripManagement\Entities\TripDeposit;
use Modules\TripManagement\Lib\DepositSettings;

/** Trip management > Booking deposits: every deposit, and the settings. */
class BookingDepositController extends Controller
{
    public const STATUSES = [
        TripDeposit::AWAITING, TripDeposit::PAID, TripDeposit::APPLIED,
        TripDeposit::FORFEITED, TripDeposit::REFUNDED, TripDeposit::EXPIRED,
    ];

    public function index(Request $request): View
    {
        $this->authorize('trip_view');
        $status = in_array($request->query('status'), self::STATUSES) ? $request->query('status') : null;
        $search = trim((string)$request->query('search', ''));
        $deposits = TripDeposit::with(['trip:id,ref_id,type,current_status', 'customer:id,first_name,last_name,phone', 'driver:id,first_name,last_name,phone'])
            ->when($status, fn($q) => $q->where('status', $status))
            ->when($search !== '', fn($q) => $q->whereHas('trip', fn($t) => $t->where('ref_id', 'like', '%' . $search . '%')))
            ->latest()
            ->paginate(paginationLimit())
            ->withQueryString();

        $since = now()->startOfMonth();
        $stats = [
            'held' => (float)TripDeposit::where('status', TripDeposit::PAID)->sum('amount'),
            'forfeited' => (float)TripDeposit::where('status', TripDeposit::FORFEITED)->where('settled_at', '>=', $since)->sum('amount'),
            'to_drivers' => (float)TripDeposit::where('status', TripDeposit::FORFEITED)->where('settled_at', '>=', $since)->sum('driver_share'),
            'refunded' => (float)TripDeposit::where('status', TripDeposit::REFUNDED)->where('settled_at', '>=', $since)->sum('refunded_amount'),
            'expired' => TripDeposit::where('status', TripDeposit::EXPIRED)->where('settled_at', '>=', $since)->count(),
        ];
        return view('tripmanagement::admin.booking-deposit.index', compact('deposits', 'status', 'search', 'stats'));
    }

    public function settings(): View
    {
        $this->authorize('trip_view');
        $settings = DepositSettings::all();
        return view('tripmanagement::admin.booking-deposit.settings', compact('settings'));
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $this->authorize('trip_edit');
        $data = $request->validate([
            'status' => 'nullable|boolean',
            'window_minutes' => 'required|integer|min:1|max:30',
            'scheduled_window_minutes' => 'required|integer|min:1|max:1440',
            'free_cancel_seconds' => 'required|integer|min:0|max:600',
            'late_minutes' => 'required|integer|min:1|max:120',
            'admin_share_percent' => 'required|numeric|min:0|max:100',
        ]);
        $save = fn(string $key, $value) => updateBusinessSetting(keyName: $key, settingType: TRIP_SETTINGS, value: (string)$value);
        $save('booking_deposit_status', $request->boolean('status') ? '1' : '0');
        $save('booking_deposit_window_minutes', $data['window_minutes']);
        $save('booking_deposit_scheduled_window_minutes', $data['scheduled_window_minutes']);
        $save('booking_deposit_free_cancel_seconds', $data['free_cancel_seconds']);
        $save('booking_deposit_late_minutes', $data['late_minutes']);
        $save('booking_deposit_admin_share_percent', $data['admin_share_percent']);
        Cache::forget(CACHE_BUSINESS_SETTINGS);
        Toastr::success(translate('Booking deposit settings saved'));
        return back();
    }
}
