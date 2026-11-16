<?php

namespace Modules\BusManagement\Http\Controllers\Web\Partner;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\BusManagement\Entities\BusStaff;
use Modules\BusManagement\Service\AgencyService;

class StaffController extends Controller
{
    public function __construct(protected AgencyService $agencies)
    {
    }

    public function index(Request $request): View
    {
        $agency = $request->attributes->get('busAgency');
        $staff = BusStaff::with('user')->where('agency_id', $agency->id)->orderByDesc('active')
            ->orderByRaw("FIELD(role, 'manager', 'counter', 'boarding')")->get();
        return view('busmanagement::partner.staff', compact('staff'));
    }

    public function store(Request $request): RedirectResponse
    {
        try {
            [$staff, $login] = $this->agencies->addStaff($request->attributes->get('busAgency'), $request->only(['name', 'email', 'role', 'password']));
        } catch (ValidationException $e) {
            return back()->withInput($request->except('password'))->withErrors($e->errors());
        }
        return redirect()->route('bus-partner.staff')->with('new_login', ['name' => $staff->user->first_name, 'login' => $login]);
    }

    public function toggle(Request $request, string $staff): RedirectResponse
    {
        $agency = $request->attributes->get('busAgency');
        $row = BusStaff::where('agency_id', $agency->id)->findOrFail($staff);
        try {
            $this->agencies->setStaffActive($agency, $row, !$row->active, $request->attributes->get('busStaff'));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
        return back()->with('toast', $row->active ? translate('Account switched on.') : translate('Account switched off: they can no longer sign in.'));
    }

    public function password(Request $request, string $staff): RedirectResponse
    {
        $agency = $request->attributes->get('busAgency');
        $row = BusStaff::with('user')->where('agency_id', $agency->id)->findOrFail($staff);
        try {
            $this->agencies->resetPassword($agency, $row, (string)$request->input('password'));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
        return back()->with('toast', translate(key: 'New password set for {name}.', replace: ['name' => $row->user->first_name]));
    }
}
