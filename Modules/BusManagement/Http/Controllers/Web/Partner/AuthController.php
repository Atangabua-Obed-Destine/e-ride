<?php

namespace Modules\BusManagement\Http\Controllers\Web\Partner;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\BusManagement\Entities\BusAgency;
use Modules\BusManagement\Entities\BusStaff;
use Modules\BusManagement\Http\Middleware\BusPartner;
use Modules\BusManagement\Service\AgencyService;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (auth()->check() && ($staff = $this->staffOf(auth()->id()))) {
            return redirect()->route(BusPartner::home($staff->role));
        }
        return view('busmanagement::partner.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login' => 'required|string|max:120',
            'password' => 'required|string|max:100',
        ]);
        $ok = Auth::attempt([
            'email' => AgencyService::emailFor($data['login']),
            'password' => $data['password'],
            'user_type' => PARTNER_USER_TYPE,
            'is_active' => 1,
        ], $request->boolean('remember', true));
        $fail = fn(string $message) => back()->withInput($request->only('login'))->withErrors(['login' => $message]);
        if (!$ok) {
            return $fail(translate('Wrong email, username or password.'));
        }
        $staff = $this->staffOf(auth()->id());
        if (!$staff) {
            Auth::logout();
            return $fail(translate('This account is not linked to a bus agency open on E-Ride. Contact E-Ride.'));
        }
        $request->session()->regenerate();
        return redirect()->intended(route(BusPartner::home($staff->role)));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('bus-partner.login');
    }

    protected function staffOf(string $userId): ?BusStaff
    {
        return BusStaff::where('user_id', $userId)->where('active', true)
            ->whereHas('agency', fn($q) => $q->where('status', '!=', BusAgency::CHECKING))->first();
    }
}
