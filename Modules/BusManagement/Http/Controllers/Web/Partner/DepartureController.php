<?php

namespace Modules\BusManagement\Http\Controllers\Web\Partner;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\BusManagement\Entities\BusDeparture;
use Modules\BusManagement\Entities\BusDestination;
use Modules\BusManagement\Entities\BusSchedule;
use Modules\BusManagement\Entities\BusSeatClaim;
use Modules\BusManagement\Entities\BusStaff;
use Modules\BusManagement\Entities\BusVehicle;
use Modules\BusManagement\Service\AgencyService;
use Modules\BusManagement\Service\ScheduleService;

class DepartureController extends Controller
{
    public function __construct(protected ScheduleService $schedules, protected AgencyService $agencies)
    {
    }

    /** The weekly timetable and a week of departures (with how full each one is). */
    public function index(Request $request): View
    {
        $agency = $request->attributes->get('busAgency');
        $start = Carbon::parse($request->query('week', today()->toDateString()))->startOfWeek();
        if ($start->lt(today()->startOfWeek()->subWeeks(4))) {
            $start = today()->startOfWeek();
        }
        $lines = BusSchedule::with(['destination', 'vehicle'])->where('agency_id', $agency->id)->orderBy('time')->get();
        $departures = BusDeparture::with(['destination', 'vehicle'])->where('agency_id', $agency->id)
            ->whereBetween('date', [$start->toDateString(), $start->copy()->addDays(6)->toDateString()])
            ->orderBy('date')->orderBy('time')->get();
        $sold = BusSeatClaim::whereIn('departure_id', $departures->pluck('id'))->selectRaw('departure_id, count(*) as n')
            ->groupBy('departure_id')->pluck('n', 'departure_id');
        $destinations = BusDestination::with(['dropPoints' => fn($q) => $q->where('active', true)])->where('agency_id', $agency->id)
            ->where('active', true)->orderBy('city')->get();
        $buses = BusVehicle::where('agency_id', $agency->id)->where('status', BusVehicle::READY)->orderBy('registration')->get();
        $canEdit = $request->attributes->get('busStaff')->role === BusStaff::MANAGER;
        return view('busmanagement::partner.departures', compact('start', 'lines', 'departures', 'sold', 'destinations', 'buses', 'canEdit'));
    }

    public function saveLine(Request $request, ?string $line = null): RedirectResponse
    {
        $agency = $request->attributes->get('busAgency');
        $existing = $line ? BusSchedule::where('agency_id', $agency->id)->findOrFail($line) : null;
        $input = $request->all();
        $input['days'] = array_sum(array_map('intval', (array)$request->input('day', [])));
        $input['auto_accept'] = $request->boolean('auto_accept');
        $input['active'] = $existing ? $request->boolean('active') : true;
        $input['vehicle_id'] = $request->input('vehicle_id') ?: null;
        try {
            $saved = $this->schedules->saveLine($agency, $input, $existing);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }
        $opened = $this->schedules->openAhead();
        $this->agencies->refreshStatus($agency);
        return redirect()->route('bus-partner.departures')->with('toast', $existing
            ? translate('Timetable saved. Departures with sold seats keep their time and bus.')
            : translate(key: 'Added to the timetable. {n} departures opened for booking.', replace: ['n' => $opened]));
    }

    public function extra(Request $request): RedirectResponse
    {
        $agency = $request->attributes->get('busAgency');
        $input = $request->all();
        $input['auto_accept'] = $request->boolean('auto_accept');
        $input['vehicle_id'] = $request->input('vehicle_id') ?: null;
        try {
            $departure = $this->schedules->addExtra($agency, $input);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }
        $this->agencies->refreshStatus($agency);
        return redirect()->route('bus-partner.departures', ['week' => $departure->date->toDateString()])->with('toast', translate('Extra departure added.'));
    }

    public function vehicle(Request $request, string $departure): RedirectResponse
    {
        $agency = $request->attributes->get('busAgency');
        $d = BusDeparture::where('agency_id', $agency->id)->findOrFail($departure);
        if (!$d->isOpen()) {
            return back()->withErrors(['vehicle_id' => translate('This departure has left or was cancelled.')]);
        }
        $vehicle = $request->filled('vehicle_id') ? BusVehicle::where('agency_id', $agency->id)->findOrFail($request->input('vehicle_id')) : null;
        try {
            $this->schedules->setVehicle($d, $vehicle);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
        $this->agencies->refreshStatus($agency);
        return redirect()->route('bus-partner.departures', ['week' => $d->date->toDateString()])
            ->with('toast', $vehicle ? translate(key: '{bus} is on this departure.', replace: ['bus' => $vehicle->registration]) : translate('Bus removed.'));
    }
}
