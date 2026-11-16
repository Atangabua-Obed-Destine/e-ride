<?php

namespace Modules\HotelManagement\Http\Controllers\Web\Partner;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\HotelManagement\Entities\HotelStaff;
use Modules\HotelManagement\Service\PartnerService;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (auth()->check() && $this->staffOf(auth()->id())) {
            return redirect()->route('hotel-partner.rooms');
        }
        return view('hotelmanagement::partner.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login' => 'required|string|max:120',
            'password' => 'required|string|max:100',
        ]);
        $ok = Auth::attempt([
            'email' => PartnerService::emailFor($data['login']),
            'password' => $data['password'],
            'user_type' => PARTNER_USER_TYPE,
            'is_active' => 1,
        ], $request->boolean('remember', true));
        $fail = fn(string $message) => back()->withInput($request->only('login'))->withErrors(['login' => $message]);
        if (!$ok) {
            return $fail(translate('Wrong email, username or password.'));
        }
        if (!$this->staffOf(auth()->id())) {
            Auth::logout();
            return $fail(translate('This account is not linked to a hotel on E-Ride. Contact E-Ride.'));
        }
        $request->session()->regenerate();
        return redirect()->intended(route('hotel-partner.rooms'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('hotel-partner.login');
    }

    protected function staffOf(string $userId): ?HotelStaff
    {
        return HotelStaff::where('user_id', $userId)->where('active', true)->whereHas('hotel')->first();
    }
}
