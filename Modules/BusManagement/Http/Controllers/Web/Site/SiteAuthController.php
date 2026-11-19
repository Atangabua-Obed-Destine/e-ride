<?php

namespace Modules\BusManagement\Http\Controllers\Web\Site;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Modules\BusManagement\Lib\BusSettings;
use Modules\Gateways\Traits\SmsGatewayForMessage;
use Modules\UserManagement\Entities\User;
use Modules\UserManagement\Service\Interfaces\CustomerServiceInterface;
use Throwable;

/**
 * Signing in on the bus website with the E-Ride account (phone and password, the same as the app), and
 * creating an account there. An SMS code at sign-up is an admin switch, off for now.
 * The website has its own sign-in (guard bus_web): it never touches the admin or partner sign-ins.
 */
class SiteAuthController extends SiteBase
{
    public const CODE_MINUTES = 10;
    public const CODE_TRIES = 5;

    public function showLogin(Request $request): Response|RedirectResponse
    {
        if ($this->user()) {
            return redirect()->intended(route('bus.web.trips'));
        }
        $this->keepIntended($request);
        return $this->page($request, 'login', ['mode' => 'login']);
    }

    public function login(Request $request): RedirectResponse
    {
        $t = $this->words($request);
        $data = $request->validate(['phone' => 'required|string|max:30', 'password' => 'required|string|max:100']);
        $phone = self::normalizePhone($data['phone']);
        $fail = fn(string $message) => back()->withInput($request->only('phone'))->withErrors(['phone' => $message]);
        $user = $phone ? User::where('phone', $phone)->where('user_type', CUSTOMER)->first() : null;
        if (!$user) {
            return $fail($t('wrong_login'));
        }
        if ($user->logged_in_via !== 'manual' && !$user->password) {
            return $fail($t('otp_user'));
        }
        // The app's rules: too many wrong passwords block the account for a while.
        $limit = (int)(businessConfig('maximum_login_hit')?->value ?? 5);
        $blockSeconds = (int)(businessConfig('temporary_login_block_time')?->value ?? 60);
        if ($user->is_temp_blocked) {
            $passed = $user->blocked_at ? Carbon::parse($user->blocked_at)->diffInSeconds(now(), true) : $blockSeconds + 1;
            if ($passed <= $blockSeconds) {
                return $fail($t('blocked', ['min' => max(1, (int)ceil(($blockSeconds - $passed) / 60))]));
            }
            $user->forceFill(['failed_attempt' => 0, 'is_temp_blocked' => 0, 'blocked_at' => null])->save();
        }
        if (!$user->password || !Hash::check($data['password'], $user->password)) {
            $user->failed_attempt += 1;
            if ($user->failed_attempt >= $limit) {
                $user->is_temp_blocked = 1;
                $user->blocked_at = now();
            }
            $user->save();
            return $fail($t('wrong_login'));
        }
        if (!$user->is_active) {
            return $fail($t('inactive'));
        }
        if ($user->failed_attempt) {
            $user->forceFill(['failed_attempt' => 0])->save();
        }
        $this->signIn($request, $user);
        return redirect()->intended(route('bus.web.trips'));
    }

    public function showRegister(Request $request): Response|RedirectResponse
    {
        if ($this->user()) {
            return redirect()->intended(route('bus.web.trips'));
        }
        $this->keepIntended($request);
        return $this->page($request, 'login', ['mode' => 'register']);
    }

    public function register(Request $request): RedirectResponse
    {
        $t = $this->words($request);
        $web = BusSettings::web();
        if (!$web['signup']) {
            return back()->withErrors(['phone' => $t('signup_in_app')]);
        }
        $data = $request->validate([
            'name' => 'required|string|min:3|max:120',
            'phone' => 'required|string|max:30',
            'password' => 'required|string|min:8|max:100',
        ], [
            'name.required' => $t('name_invalid'), 'name.min' => $t('name_invalid'),
            'password.min' => $t('password_short'), 'password.required' => $t('password_short'),
        ]);
        $back = fn(string $field, string $message) => back()->withInput($request->only('name', 'phone'))->withErrors([$field => $message]);
        $phone = self::normalizePhone($data['phone']);
        if (!$phone) {
            return $back('phone', $t('phone_invalid'));
        }
        $existing = User::where('phone', $phone)->first();
        if ($existing) {
            return $back('phone', $existing->logged_in_via === 'otp' && !$existing->password ? $t('otp_user') : $t('phone_taken'));
        }
        $name = trim(preg_replace('/\s+/', ' ', $data['name']));

        if ($web['signup_code']) {
            $code = (string)random_int(100000, 999999);
            try {
                $sent = (new class {
                    use SmsGatewayForMessage;
                })::send($phone, $t('signup_code_sms', ['code' => $code]));
            } catch (Throwable $e) {
                $sent = 'error';
            }
            if ($sent !== 'success') {
                Log::info('Bus website sign-up code not sent', ['result' => $sent]);
                return $back('phone', $t('code_not_sent'));
            }
            $request->session()->put('bus_signup', [
                'name' => $name, 'phone' => $phone, 'password' => Crypt::encryptString($data['password']),
                'code' => Hash::make($code), 'until' => now()->addMinutes(self::CODE_MINUTES)->timestamp, 'tries' => 0,
            ]);
            return redirect()->route('bus.web.register.code');
        }

        $user = $this->create($name, $phone, $data['password'], $t->lang);
        if (!$user) {
            return $back('phone', $t('phone_taken'));
        }
        $this->signIn($request, $user);
        return redirect()->intended(route('bus.web.trips'));
    }

    public function showCode(Request $request): Response|RedirectResponse
    {
        $pending = $request->session()->get('bus_signup');
        if (!$pending) {
            return redirect()->route('bus.web.register');
        }
        return $this->page($request, 'login', ['mode' => 'code', 'codePhone' => $pending['phone']]);
    }

    public function verifyCode(Request $request): RedirectResponse
    {
        $t = $this->words($request);
        $pending = $request->session()->get('bus_signup');
        if (!$pending) {
            return redirect()->route('bus.web.register');
        }
        $data = $request->validate(['code' => 'required|string|max:10']);
        if (now()->timestamp > $pending['until'] || $pending['tries'] >= self::CODE_TRIES) {
            $request->session()->forget('bus_signup');
            return redirect()->route('bus.web.register')->withErrors(['phone' => $t('code_expired')]);
        }
        if (!Hash::check(preg_replace('/\D+/', '', $data['code']), $pending['code'])) {
            $pending['tries']++;
            $request->session()->put('bus_signup', $pending);
            return back()->withErrors(['code' => $t('code_wrong')]);
        }
        $request->session()->forget('bus_signup');
        $user = $this->create($pending['name'], $pending['phone'], Crypt::decryptString($pending['password']), $t->lang, true);
        if (!$user) {
            return redirect()->route('bus.web.login')->withErrors(['phone' => $t('phone_taken')]);
        }
        $this->signIn($request, $user);
        return redirect()->intended(route('bus.web.trips'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('bus_web')->logout();
        $request->session()->forget(['bus_signup']);
        $request->session()->regenerateToken();
        return redirect()->route('bus.web.home');
    }

    /** "6 77 12 34 56", "677123456", "237677123456", "+237 677..." to "+237677123456"; null when it is not a number. */
    public static function normalizePhone(string $input): ?string
    {
        $input = trim($input);
        $digits = preg_replace('/\D+/', '', $input);
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
            $input = '+' . $digits;
        }
        if (strlen($digits) === 9 && in_array($digits[0], ['6', '2'], true)) {
            return '+237' . $digits;
        }
        if (strlen($digits) === 12 && str_starts_with($digits, '237')) {
            return '+' . $digits;
        }
        if (str_starts_with($input, '+') && strlen($digits) >= 8 && strlen($digits) <= 15) {
            return '+' . $digits;
        }
        return null;
    }

    protected function create(string $name, string $phone, string $password, string $lang, bool $verified = false): ?User
    {
        $parts = explode(' ', $name, 2);
        try {
            $user = app(CustomerServiceInterface::class)->create([
                'first_name' => $parts[0],
                'last_name' => $parts[1] ?? '',
                'phone' => $phone,
                'password' => $password,
                'current_language_key' => $lang,
            ]);
        } catch (Throwable $e) {
            // Two sign-ups with the same number at once: the database keeps one.
            Log::info('Bus website sign-up refused', ['error' => $e->getMessage()]);
            return null;
        }
        if ($user && $verified) {
            // Read back first: the user model logs changes against what it loaded.
            $user = $user->fresh();
            $user->forceFill(['phone_verified_at' => now()])->save();
        }
        return $user;
    }

    protected function signIn(Request $request, User $user): void
    {
        // A fresh copy: the user model logs changes against what it loaded (signing in sets the remember token).
        Auth::guard('bus_web')->login($user->fresh() ?? $user, true);
        $request->session()->regenerate();
    }

    /** ?next=/bus/... keeps where to go back after signing in (only pages of this site). */
    protected function keepIntended(Request $request): void
    {
        $next = (string)$request->query('next', '');
        if ($next !== '' && str_starts_with($next, '/bus') && !str_starts_with($next, '//')) {
            $request->session()->put('url.intended', url($next));
        }
    }
}
