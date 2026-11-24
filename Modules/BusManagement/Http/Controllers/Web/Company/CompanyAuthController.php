<?php

namespace Modules\BusManagement\Http\Controllers\Web\Company;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\BusManagement\Entities\BusCompanyStaff;
use Modules\BusManagement\Service\AgencyService;

/** Sign-in for a bus company's head office (director, regional manager, accountant). */
class CompanyAuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (auth()->check() && $this->staffOf(auth()->id())) {
            return redirect()->route('bus-company.overview');
        }
        return view('busmanagement::partner.login', [
            'portal' => 'company',
            'features' => [
                translate('Every branch live: departures, seats, requests and money taken today'),
                translate('Reports by branch, route, agent and day'),
                translate('Head office books, and the whole company statements'),
            ],
            'heading' => translate('E-Ride for bus companies'),
            'sub' => translate('Your head office: every branch, its departures, sales and agents, in one place.'),
            'action' => route('bus-company.login.submit'),
            'help' => translate('Lost your password: your director can set a new one. Directors: ask the E-Ride team.'),
        ]);
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
        if (!$this->staffOf(auth()->id())) {
            Auth::logout();
            return $fail(translate('This account is not a head office login of a bus company. Branch staff sign in at the agency portal.'));
        }
        $request->session()->regenerate();
        return redirect()->intended(route('bus-company.overview'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('bus-company.login');
    }

    protected function staffOf(string $userId): ?BusCompanyStaff
    {
        return BusCompanyStaff::where('user_id', $userId)->where('active', true)->first();
    }
}
