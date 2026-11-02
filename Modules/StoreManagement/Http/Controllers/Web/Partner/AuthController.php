<?php

namespace Modules\StoreManagement\Http\Controllers\Web\Partner;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (auth()->check() && auth()->user()->user_type === PARTNER_USER_TYPE) {
            return redirect()->route('partner.orders');
        }
        return view('storemanagement::partner.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $ok = Auth::attempt([
            'email' => strtolower(trim($credentials['email'])),
            'password' => $credentials['password'],
            'user_type' => PARTNER_USER_TYPE,
            'is_active' => 1,
        ], $request->boolean('remember', true));

        if (!$ok) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => translate('Wrong email or password, or this partner account is switched off.')]);
        }

        $request->session()->regenerate();
        return redirect()->intended(route('partner.orders'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('partner.login');
    }
}
