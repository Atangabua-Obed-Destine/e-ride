<?php

namespace Modules\BusManagement\Http\Controllers\Web\Partner;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\BusManagement\Entities\BusAgency;
use Modules\BusManagement\Entities\BusDeparture;
use Modules\BusManagement\Entities\BusVehicle;
use Modules\BusManagement\Service\FleetService;

class FleetController extends Controller
{
    public function __construct(protected FleetService $fleet)
    {
    }

    public function index(Request $request): View
    {
        $agency = $this->agency($request);
        $buses = BusVehicle::usableByAgency($agency)->orderByRaw("FIELD(status, 'ready', 'garage', 'retired')")->orderBy('registration')->get();
        $bus = $request->filled('bus') ? $buses->firstWhere('id', $request->query('bus')) : null;
        $creating = !$bus && ($request->boolean('new') || $buses->isEmpty());
        if (!$bus && !$creating) {
            $bus = $buses->first();
        }
        $upcoming = $bus ? BusDeparture::where('vehicle_id', $bus->id)->whereIn('status', BusDeparture::OPEN)->where('date', '>=', today())->count() : 0;
        return view('busmanagement::partner.fleet', compact('buses', 'bus', 'creating', 'upcoming'));
    }

    public function save(Request $request, ?string $bus = null): RedirectResponse
    {
        $agency = $this->agency($request);
        $vehicle = $bus ? BusVehicle::usableByAgency($agency)->findOrFail($bus) : null;
        $input = $request->all();
        foreach (['is_double_decker', 'has_rear_seats'] as $flag) {
            $input[$flag] = $request->boolean($flag);
        }
        $input['door_positions'] = array_values(array_filter((array)$request->input('door_positions', []), fn($d) => filled($d['row'] ?? null)));
        try {
            $saved = $this->fleet->saveVehicle($agency, $input, $vehicle);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }
        return redirect()->route('bus-partner.fleet', ['bus' => $saved->id])
            ->with('toast', $vehicle ? translate('Bus saved. Departures with sold seats keep their plan.') : translate('Bus added.'));
    }

    protected function agency(Request $request): BusAgency
    {
        return $request->attributes->get('busAgency');
    }
}
