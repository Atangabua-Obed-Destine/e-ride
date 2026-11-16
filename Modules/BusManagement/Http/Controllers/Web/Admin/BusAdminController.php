<?php

namespace Modules\BusManagement\Http\Controllers\Web\Admin;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\BusManagement\Entities\BusAgency;
use Modules\BusManagement\Entities\BusBooking;
use Modules\BusManagement\Entities\BusDeparture;
use Modules\BusManagement\Entities\BusStaff;
use Modules\BusManagement\Lib\BusSettings;
use Modules\BusManagement\Service\AgencyService;
use Modules\PlaceManagement\Service\Interfaces\PlaceServiceInterface;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** E-Ride admin: find bus agencies on Google, verify them, open their portal, set E-Ride's rates and the bus settings. */
class BusAdminController extends Controller
{
    public function __construct(protected AgencyService $agencies, protected PlaceServiceInterface $places)
    {
    }

    public function index(Request $request): View
    {
        $agencies = BusAgency::withCount(['staff', 'vehicles'])->orderByRaw("FIELD(status, 'checking', 'setup', 'live', 'paused')")->orderBy('name')->get();
        $monthStart = now()->startOfMonth();
        $appTickets = BusBooking::where('source', BusBooking::APP)->where('status', BusBooking::PAID)->where('paid_at', '>=', $monthStart)
            ->selectRaw('agency_id, count(*) as n')->groupBy('agency_id')->pluck('n', 'agency_id');
        $openDepartures = BusDeparture::whereIn('status', BusDeparture::OPEN)->where('date', '>=', today())->whereNotNull('vehicle_id')
            ->selectRaw('agency_id, count(*) as n')->groupBy('agency_id')->pluck('n', 'agency_id');
        // A Google search costs money: it only runs when the admin asks.
        $lat = (float)$request->query('latitude', 5.9597);
        $lng = (float)$request->query('longitude', 10.1460);
        $google = $request->boolean('load') ? $this->places->googlePreview($lat, $lng, 'bus') : false;
        $known = $agencies->pluck('id', 'google_place_id');
        $settings = BusSettings::all();
        return view('busmanagement::admin.agencies', compact('agencies', 'appTickets', 'openDepartures', 'google', 'known', 'settings', 'lat', 'lng'));
    }

    public function start(Request $request): RedirectResponse
    {
        try {
            $agency = $this->agencies->startFromGoogle($request->all(), auth()->id());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
        return redirect()->route('admin.bus.show', $agency->id);
    }

    public function show(BusAgency $agency): View
    {
        $agency->loadCount(['vehicles', 'destinations', 'schedules']);
        $managers = BusStaff::with('user')->where('agency_id', $agency->id)->where('role', BusStaff::MANAGER)->get();
        $settings = BusSettings::all();
        return view('busmanagement::admin.agency', compact('agency', 'managers', 'settings'));
    }

    public function details(Request $request, BusAgency $agency): RedirectResponse
    {
        try {
            $this->agencies->saveDetails($agency, $request->all());
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }
        Toastr::success(translate('Saved'));
        return back();
    }

    public function check(Request $request, BusAgency $agency): RedirectResponse
    {
        $data = $request->validate(['check' => 'required|string', 'done' => 'required|boolean']);
        try {
            $this->agencies->setCheck($agency, $data['check'], (bool)$data['done'], auth()->id());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
        return back();
    }

    /** The transport licence photo: kept private, seen by E-Ride staff only. */
    public function licence(Request $request, BusAgency $agency): RedirectResponse
    {
        $request->validate(['licence' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:8192']);
        $path = $request->file('licence')->store('bus/licences', 'local');
        if ($agency->licence_file) {
            Storage::disk('local')->delete($agency->licence_file);
        }
        $agency->update(['licence_file' => $path]);
        $this->agencies->setCheck($agency, 'licence', true, auth()->id());
        Toastr::success(translate('Licence kept. Only E-Ride staff can see it.'));
        return back();
    }

    public function licenceFile(BusAgency $agency): StreamedResponse
    {
        abort_unless($agency->licence_file && Storage::disk('local')->exists($agency->licence_file), 404);
        return Storage::disk('local')->response($agency->licence_file);
    }

    public function verify(Request $request, BusAgency $agency): RedirectResponse
    {
        try {
            [$agency, $login] = $this->agencies->verify($agency, $request->only(['name', 'email', 'password']), auth()->id());
        } catch (ValidationException $e) {
            return back()->withInput($request->except('password'))->withErrors($e->errors());
        }
        return back()->with('portal_login', ['login' => $login, 'url' => route('bus-partner.login')]);
    }

    public function pause(Request $request, BusAgency $agency): RedirectResponse
    {
        $data = $request->validate(['paused' => 'required|boolean', 'note' => 'nullable|string|max:300']);
        try {
            $this->agencies->setPaused($agency, (bool)$data['paused'], $data['note'] ?? null);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
        Toastr::success($data['paused'] ? translate('Online booking paused. Their counter keeps working.') : translate('Online booking back on.'));
        return back();
    }

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'answer_minutes' => 'required|integer|min:2|max:60',
            'pay_minutes' => 'required|integer|min:5|max:60',
            'open_days' => 'required|integer|min:1|max:60',
            'commission_app_pct' => 'required|numeric|min:0|max:50',
            'commission_counter_pct' => 'required|numeric|min:0|max:50',
            'statement_due_day' => 'required|integer|min:1|max:28',
            'late_pause_days' => 'required|integer|min:1|max:60',
            'refund_hours' => 'required|integer|min:6|max:168',
            'decline_pause_pct' => 'required|integer|min:0|max:100',
            'max_seats' => 'required|integer|min:1|max:10',
        ]);
        $data['enabled'] = $request->boolean('enabled') ? 1 : 0;
        foreach ($data as $key => $value) {
            updateBusinessSetting(keyName: 'bus_' . $key, settingType: BusSettings::TYPE, value: (string)$value);
        }
        Toastr::success(translate('Bus settings saved'));
        return back();
    }
}
