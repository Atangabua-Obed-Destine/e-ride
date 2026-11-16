<?php

namespace Modules\HotelManagement\Http\Controllers\Web\Partner;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Modules\HotelManagement\Entities\HotelStaff;
use Modules\HotelManagement\Service\PartnerService;
use Modules\UserManagement\Entities\User;

/** The manager's team: receptionists and other managers, each with their own login. */
class StaffController extends Controller
{
    public function __construct(protected PartnerService $partners)
    {
    }

    public function index(Request $request): View
    {
        $hotel = $request->attributes->get('hotel');
        $staff = HotelStaff::with('user')->where('hotel_id', $hotel->id)->orderByDesc('active')->orderBy('role')->get();
        return view('hotelmanagement::partner.staff', compact('staff'));
    }

    public function store(Request $request): RedirectResponse
    {
        $hotel = $request->attributes->get('hotel');
        try {
            [, $login] = $this->partners->addStaff($hotel, $request->only(['name', 'email', 'role', 'password']));
        } catch (ValidationException $e) {
            return back()->withInput($request->except('password'))->withErrors($e->errors());
        }
        return back()->with('success', translate(key: 'Added. They sign in at this address with: {login}', replace: ['login' => $login]));
    }

    public function toggle(Request $request, int $staff): RedirectResponse
    {
        $hotel = $request->attributes->get('hotel');
        $row = HotelStaff::where('hotel_id', $hotel->id)->findOrFail($staff);
        try {
            $this->partners->setStaffActive($hotel, $row, !$row->active, $request->attributes->get('hotelStaff'));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }
        return back()->with('toast', $row->active ? translate('Access turned on.') : translate('Access turned off.'));
    }

    public function password(Request $request, int $staff): RedirectResponse
    {
        $hotel = $request->attributes->get('hotel');
        $row = HotelStaff::where('hotel_id', $hotel->id)->findOrFail($staff);
        $data = $request->validate(['password' => 'required|string|min:8|max:100']);
        User::whereKey($row->user_id)->update(['password' => Hash::make($data['password'])]);
        return back()->with('toast', translate('New password set.'));
    }
}
