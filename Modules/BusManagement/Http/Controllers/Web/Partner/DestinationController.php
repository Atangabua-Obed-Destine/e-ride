<?php

namespace Modules\BusManagement\Http\Controllers\Web\Partner;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\BusManagement\Entities\BusDestination;
use Modules\BusManagement\Lib\BusSettings;
use Modules\BusManagement\Service\FleetService;

class DestinationController extends Controller
{
    public function __construct(protected FleetService $fleet)
    {
    }

    public function index(Request $request): View
    {
        $agency = $request->attributes->get('busAgency');
        $destinations = BusDestination::with(['dropPoints' => fn($q) => $q->where('active', true)])
            ->where('agency_id', $agency->id)->orderBy('sort')->orderBy('city')->get();
        $rules = BusSettings::rules($agency->rules);
        return view('busmanagement::partner.destinations', compact('destinations', 'rules'));
    }

    public function save(Request $request, ?string $destination = null): RedirectResponse
    {
        $agency = $request->attributes->get('busAgency');
        $existing = $destination ? BusDestination::where('agency_id', $agency->id)->findOrFail($destination) : null;
        $input = $request->all();
        $input['points'] = array_values(array_filter((array)$request->input('points', []), fn($p) => filled($p['name'] ?? null)));
        // An unticked switch sends nothing: an existing destination is then not offered; a new one always is.
        $input['active'] = $existing ? $request->boolean('active') : true;
        try {
            $saved = $this->fleet->saveDestination($agency, $input, $existing);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors())->with('open', $destination ?? 'new');
        }
        return redirect()->route('bus-partner.destinations')->with('toast', translate(key: '{city} saved.', replace: ['city' => $saved->city]));
    }

    /** Bags, seats kept for the counter, online cut-off and the cancellation rules passengers see before booking. */
    public function rules(Request $request): RedirectResponse
    {
        $agency = $request->attributes->get('busAgency');
        $data = $request->validate([
            'free_bags' => 'required|integer|min:0|max:5',
            'bag_kg' => 'required|integer|min:5|max:50',
            'extra_bag_fee' => 'required|integer|min:0|max:100000',
            'counter_seats' => 'required|integer|min:0|max:80',
            'online_cutoff_minutes' => 'required|integer|min:0|max:720',
            'id_check' => 'nullable|in:eride,agents,number',
            'tickets' => 'nullable|in:app,collect',
            'tier_full_hours' => 'required|integer|min:0|max:168',
            'tier_half_hours' => 'required|integer|min:0|max:168',
            'tier_half_pct' => 'required|integer|min:0|max:100',
        ]);
        if ($data['tier_half_hours'] > $data['tier_full_hours']) {
            return back()->withInput()->withErrors(['tier_half_hours' => translate('The second deadline must come after the first (fewer hours before departure).')]);
        }
        $agency->update(['rules' => [
            'free_bags' => $data['free_bags'],
            'bag_kg' => $data['bag_kg'],
            'extra_bag_fee' => $data['extra_bag_fee'],
            'counter_seats' => $data['counter_seats'],
            'online_cutoff_minutes' => $data['online_cutoff_minutes'],
            'require_id' => true,
            'id_check' => $data['id_check'] ?? BusSettings::rules($agency->rules)['id_check'],
            'tickets' => $data['tickets'] ?? BusSettings::rules($agency->rules)['tickets'],
            'cancel_tiers' => [[$data['tier_full_hours'], 100], [$data['tier_half_hours'], $data['tier_half_pct']], [0, 0]],
        ]]);
        return redirect()->route('bus-partner.destinations')->with('toast', translate('Booking rules saved. Passengers see them before they book.'));
    }
}
