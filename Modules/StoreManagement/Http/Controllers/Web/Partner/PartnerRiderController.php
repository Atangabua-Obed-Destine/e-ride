<?php

namespace Modules\StoreManagement\Http\Controllers\Web\Partner;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\StoreManagement\Entities\Store;
use Modules\StoreManagement\Lib\StoreRiders;
use Modules\StoreManagement\Lib\StoreSettings;
use Modules\UserManagement\Entities\User;

/**
 * "My riders": the E-Ride riders this kitchen wants for its deliveries, and
 * whether they are rung first or alone. Each login manages its own kitchen.
 */
class PartnerRiderController extends Controller
{
    protected function store(Request $request): Store
    {
        return $request->attributes->get('partnerStore');
    }

    public function index(Request $request): View
    {
        abort_unless(StoreSettings::all()['my_riders_status'], 404);
        $store = $this->store($request);
        return view('storemanagement::partner.riders', [
            'store' => $store,
            'riders' => StoreRiders::list($store),
            'candidates' => StoreRiders::candidates($store),
            'firstSeconds' => StoreSettings::all()['my_riders_first_seconds'],
            'max' => StoreRiders::MAX,
        ]);
    }

    /** Add a rider who delivered for the store (driver_id), or one it knows by phone. */
    public function add(Request $request): RedirectResponse
    {
        abort_unless(StoreSettings::all()['my_riders_status'], 404);
        $data = $request->validate([
            'driver_id' => 'nullable|uuid',
            'phone' => 'nullable|string|max:25|required_without:driver_id',
        ]);
        $store = $this->store($request);
        try {
            if (!empty($data['driver_id'])) {
                // Only riders this kitchen already worked with can be added without their number.
                $known = collect(StoreRiders::candidates($store, 200))->pluck('driver_id')->contains($data['driver_id']);
                $row = StoreRiders::add($store, $known ? User::find($data['driver_id']) : null);
            } else {
                $row = StoreRiders::addByPhone($store, $data['phone']);
            }
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }
        return back()->with('toast', translate(key: '{rider} is now one of your riders', replace: ['rider' => trim((string)$row->driver?->first_name) ?: translate('The rider')]));
    }

    public function remove(Request $request, string $driver): RedirectResponse
    {
        StoreRiders::remove($this->store($request), $driver);
        return back()->with('toast', translate('Rider removed from your list'));
    }

    /** all: every rider nearby. first: my riders, then everyone. only: my riders alone. */
    public function mode(Request $request): RedirectResponse
    {
        abort_unless(StoreSettings::all()['my_riders_status'], 404);
        $data = $request->validate(['riders_mode' => 'required|in:all,first,only']);
        $store = $this->store($request);
        $store->riders_mode = $data['riders_mode'];
        $store->save();
        return back()->with('toast', translate('Saved'));
    }
}
