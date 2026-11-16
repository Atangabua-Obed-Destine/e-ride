<?php

namespace Modules\BusManagement\Service;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\BusManagement\Entities\BusAgency;
use Modules\BusManagement\Entities\BusDeparture;
use Modules\BusManagement\Entities\BusStaff;
use Modules\Gateways\Services\FapshiClient;
use Modules\UserManagement\Entities\User;
use Throwable;

/**
 * An agency's people (manager, counter and boarding staff) and its own Fapshi account.
 */
class AgencyService
{
    /** Staff without an email sign in with a username; it is kept as <username>@ this domain. */
    public const USERNAME_DOMAIN = 'bus-staff.eride';

    /** What E-Ride checks before opening an agency's portal. */
    public const CHECKS = ['licence', 'office', 'manager_id'];

    // ------------------------------------------------------------- E-Ride admin

    /** Starts checking an agency from its Google listing (one agency per Google place). */
    public function startFromGoogle(array $input, string $adminId): BusAgency
    {
        $data = Validator::make($input, [
            'google_place_id' => 'required|string|max:191|regex:/^[A-Za-z0-9_-]+$/',
            'name' => 'required|string|max:120',
            'address' => 'nullable|string|max:191',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'phone' => 'nullable|string|max:30',
        ])->validate();
        if ($existing = BusAgency::where('google_place_id', $data['google_place_id'])->first()) {
            return $existing;
        }
        return BusAgency::create([
            'name' => trim($data['name']),
            'slug' => $this->uniqueSlug($data['name']),
            'google_place_id' => $data['google_place_id'],
            'origin_city' => 'Bamenda',
            'address' => $data['address'] ?? null,
            'lat' => $data['lat'] ?? null,
            'lng' => $data['lng'] ?? null,
            'phone' => $data['phone'] ?? null,
            'status' => BusAgency::CHECKING,
            'webhook_code' => Str::lower(Str::random(16)),
            'commission_app_pct' => \Modules\BusManagement\Lib\BusSettings::all()['commission_app_pct'],
            'commission_counter_pct' => \Modules\BusManagement\Lib\BusSettings::all()['commission_counter_pct'],
            'checks' => [],
        ]);
    }

    /** The agency's details and E-Ride's rates (any time). */
    public function saveDetails(BusAgency $agency, array $input): BusAgency
    {
        $data = Validator::make($input, [
            'name' => 'required|string|max:120',
            'origin_city' => 'required|string|max:80',
            'address' => 'nullable|string|max:191',
            'phone' => 'nullable|string|max:30',
            'about' => 'nullable|string|max:600',
            'commission_app_pct' => 'required|numeric|min:0|max:50',
            'commission_counter_pct' => 'required|numeric|min:0|max:50',
        ], [], [
            'origin_city' => translate('departure city'),
            'commission_app_pct' => translate('commission on app tickets'),
            'commission_counter_pct' => translate('commission on counter tickets'),
        ])->validate();
        $agency->fill($data);
        if ($agency->isDirty('name')) {
            $agency->slug = $this->uniqueSlug($data['name'], $agency->id);
        }
        $agency->save();
        return $agency;
    }

    /** Ticks (or unticks) a verification check, with who and when. */
    public function setCheck(BusAgency $agency, string $check, bool $done, string $adminId): BusAgency
    {
        if (!in_array($check, self::CHECKS, true)) {
            throw ValidationException::withMessages(['check' => translate('Unknown check.')]);
        }
        $checks = $agency->checks ?? [];
        if ($done) {
            $checks[$check] = ['by' => $adminId, 'at' => now()->toIso8601String()];
        } else {
            unset($checks[$check]);
        }
        $agency->update(['checks' => $checks]);
        return $agency;
    }

    /**
     * Verified: every check done, the manager's portal login made. The portal opens ("setup"); the agency
     * goes live by itself once its Fapshi works and a departure with a bus is open. Returns [agency, login].
     */
    public function verify(BusAgency $agency, array $manager, string $adminId): array
    {
        if ($agency->status !== BusAgency::CHECKING) {
            throw ValidationException::withMessages(['agency' => translate('This agency is already verified.')]);
        }
        $missing = array_diff(self::CHECKS, array_keys($agency->checks ?? []));
        if ($missing) {
            throw ValidationException::withMessages(['checks' => translate('Tick every check first: the licence, the office visit and the manager ID.')]);
        }
        return DB::transaction(function () use ($agency, $manager, $adminId) {
            [$staff, $login] = $this->addStaff($agency, $manager + ['role' => BusStaff::MANAGER]);
            $agency->update(['status' => BusAgency::SETUP, 'verified_at' => now(), 'verified_by' => $adminId]);
            return [$agency->fresh(), $login];
        });
    }

    /** E-Ride pauses online booking (the counter keeps working), or lifts its own pause. */
    public function setPaused(BusAgency $agency, bool $paused, ?string $note, string $reason = 'manual'): BusAgency
    {
        if ($paused) {
            if ($agency->status === BusAgency::CHECKING) {
                throw ValidationException::withMessages(['agency' => translate('Verify the agency first.')]);
            }
            $agency->update(['status' => BusAgency::PAUSED, 'pause_reason' => $reason, 'pause_note' => $note]);
        } elseif ($agency->status === BusAgency::PAUSED) {
            // Back to setting up: it goes live by itself when Fapshi works and a departure is open.
            $agency->update(['status' => BusAgency::SETUP, 'pause_reason' => null, 'pause_note' => null]);
            $this->refreshStatus($agency);
        }
        return $agency->fresh();
    }

    protected function uniqueSlug(string $name, ?string $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'agency';
        $slug = $base;
        for ($i = 2; BusAgency::where('slug', $slug)->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))->exists(); $i++) {
            $slug = $base . '-' . $i;
        }
        return $slug;
    }

    // ------------------------------------------------------------- staff

    /**
     * Adds someone to the agency's portal. With an email they sign in with it; without, with a username
     * made from their name and the agency (eric.ve31k8). Returns [staff, login].
     */
    public function addStaff(BusAgency $agency, array $input): array
    {
        $data = Validator::make($input, [
            'name' => 'required|string|max:80',
            'email' => 'nullable|email|max:120',
            'role' => ['required', Rule::in(BusStaff::ROLES)],
            'password' => 'required|string|min:8|max:100',
        ], [], ['name' => translate('name'), 'password' => translate('password')])->validate();

        $email = filled($data['email'] ?? null) ? strtolower(trim($data['email'])) : null;
        if ($email && User::where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => translate('This email already has an E-Ride account. Use another email, or leave it empty for a username.')]);
        }
        $login = $email ?? $this->freeUsername($data['name'], $agency);

        return DB::transaction(function () use ($agency, $data, $email, $login) {
            $user = User::create([
                'first_name' => trim($data['name']),
                'email' => $email ?? ($login . '@' . self::USERNAME_DOMAIN),
                'password' => Hash::make($data['password']),
                'user_type' => PARTNER_USER_TYPE,
                'is_active' => 1,
            ]);
            $staff = BusStaff::create(['agency_id' => $agency->id, 'user_id' => $user->id, 'role' => $data['role'], 'active' => true]);
            return [$staff, $login];
        });
    }

    public function setStaffActive(BusAgency $agency, BusStaff $staff, bool $active, BusStaff $by): BusStaff
    {
        $this->own($agency, $staff);
        if ($staff->id === $by->id) {
            throw ValidationException::withMessages(['staff' => translate('You cannot switch off your own account.')]);
        }
        if (!$active && $staff->role === BusStaff::MANAGER
            && BusStaff::where('agency_id', $agency->id)->where('role', BusStaff::MANAGER)->where('active', true)->count() <= 1) {
            throw ValidationException::withMessages(['staff' => translate('The agency needs at least one manager.')]);
        }
        $staff->update(['active' => $active]);
        return $staff;
    }

    public function resetPassword(BusAgency $agency, BusStaff $staff, string $password): void
    {
        $this->own($agency, $staff);
        if (mb_strlen($password) < 8) {
            throw ValidationException::withMessages(['password' => translate('Use at least 8 characters.')]);
        }
        $staff->user->update(['password' => Hash::make($password)]);
    }

    /** What a staff member types to sign in: their email, or the username part of the staff address. */
    public static function loginOf(User $user): string
    {
        return str_ends_with((string)$user->email, '@' . self::USERNAME_DOMAIN) ? Str::before($user->email, '@') : (string)$user->email;
    }

    /** The email to try for what was typed at sign-in. */
    public static function emailFor(string $typed): string
    {
        $typed = strtolower(trim($typed));
        return str_contains($typed, '@') ? $typed : $typed . '@' . self::USERNAME_DOMAIN;
    }

    protected function freeUsername(string $name, BusAgency $agency): string
    {
        $base = Str::of(Str::ascii($name))->lower()->replaceMatches('/[^a-z0-9]+/', '')->limit(12, '')->value() ?: 'staff';
        $suffix = strtolower(substr($agency->webhook_code, 0, 5));
        $login = $base . '.' . $suffix;
        for ($i = 2; User::where('email', $login . '@' . self::USERNAME_DOMAIN)->exists(); $i++) {
            $login = $base . $i . '.' . $suffix;
        }
        return $login;
    }

    protected function own(BusAgency $agency, BusStaff $staff): void
    {
        if ((string)$staff->agency_id !== (string)$agency->id) {
            abort(404);
        }
    }

    // ------------------------------------------------------------- Fapshi

    /** Saves the agency's Fapshi keys. An empty key or secret keeps the saved one. Changing anything needs a new test. */
    public function saveFapshi(BusAgency $agency, array $input): BusAgency
    {
        $data = Validator::make($input, [
            'api_user' => 'required|string|max:120',
            'api_key' => 'nullable|string|max:200',
            'webhook_secret' => 'nullable|string|max:200',
            'mode' => ['required', Rule::in(['test', 'live'])],
        ], [], ['api_user' => translate('API user'), 'api_key' => translate('API key')])->validate();

        $old = $agency->fapshiValues() ?? (is_array($agency->fapshi) ? $agency->fapshi : []);
        $new = [
            'api_user' => trim($data['api_user']),
            'api_key' => filled($data['api_key'] ?? null) ? trim($data['api_key']) : ($old['api_key'] ?? null),
            'webhook_secret' => filled($data['webhook_secret'] ?? null) ? trim($data['webhook_secret']) : ($old['webhook_secret'] ?? null),
            'mode' => $data['mode'],
        ];
        if (!filled($new['api_key'])) {
            throw ValidationException::withMessages(['api_key' => translate('Enter the API key.')]);
        }
        $changed = $new != array_intersect_key($old, $new);
        $agency->fapshi = $new;
        if ($changed) {
            $agency->fapshi_ok_at = null;
        }
        $agency->save();
        return $agency;
    }

    public function fapshiClient(BusAgency $agency): ?FapshiClient
    {
        $values = $agency->fapshiValues();
        return $values ? new FapshiClient($values, $values['mode'] ?? 'test') : null;
    }

    /** Checks the keys against Fapshi (the balance). Returns [ok, message]; ok marks the account connected. */
    public function testFapshi(BusAgency $agency): array
    {
        $client = $this->fapshiClient($agency);
        if (!$client) {
            return [false, translate('Save your API user and API key first.')];
        }
        $host = parse_url($client->baseUrl(), PHP_URL_HOST);
        if ($looksLike = $client->credentialEnvironmentMismatch()) {
            return [false, $looksLike === 'sandbox'
                ? translate('This is a sandbox (test) key, but the mode is Live.')
                : translate('This is a live key, but the mode is Test.')];
        }
        if (!filled($agency->fapshiValues()['webhook_secret'] ?? null)) {
            return [false, translate('Add the webhook secret too: E-Ride uses it to check that payment news really comes from Fapshi.')];
        }
        try {
            $response = $client->balance();
        } catch (Throwable) {
            return [false, translate(key: 'Could not reach {host}. Try again in a minute.', replace: ['host' => $host])];
        }
        if ($response->successful()) {
            $agency->update(['fapshi_ok_at' => now()]);
            $this->refreshStatus($agency);
            return [true, $client->environment() === 'live'
                ? translate('Connected to Fapshi (live). Passengers will pay straight into this account.')
                : translate('Connected to Fapshi (test). Payments are only pretend until you switch to Live.')];
        }
        if ($response->status() === 403) {
            return [false, translate('Fapshi refused the E-Ride server (403). In your Fapshi dashboard, allow the E-Ride server address in the IP whitelist, or ask E-Ride for it.')];
        }
        if (in_array($response->status(), [400, 401], true)) {
            return [false, translate('Fapshi rejected the API user or API key. Check that both are from the same service and mode.')];
        }
        return [false, translate(key: 'Fapshi answered {code}. Try again, or check your keys.', replace: ['code' => $response->status()])];
    }

    /**
     * Testing only: the agency uses E-Ride's own Fapshi sandbox keys (copied on the server, never shown), so a test
     * agency can take pretend payments. Refused unless they really are sandbox keys. Returns [ok, message].
     */
    public function useEridesSandbox(BusAgency $agency): array
    {
        $row = DB::table('settings')->where('key_name', 'fapshi')->where('settings_type', PAYMENT_CONFIG)->first();
        $values = $row ? (json_decode((string)$row->test_values, true) ?: []) : [];
        if (!filled($values['api_user'] ?? null) || !filled($values['api_key'] ?? null)) {
            return [false, translate('E-Ride has no Fapshi sandbox keys saved (Payment Methods, test values).')];
        }
        if (!str_starts_with(trim($values['api_key']), 'FAK_TEST_')) {
            return [false, translate('Refused: the saved test key is not a Fapshi sandbox key.')];
        }
        $agency->fapshi = [
            'api_user' => trim($values['api_user']),
            'api_key' => trim($values['api_key']),
            // Webhooks of this sandbox go to E-Ride's own Fapshi webhook, which settles bus payments too.
            'webhook_secret' => filled($values['webhook_secret'] ?? null) ? trim($values['webhook_secret']) : Str::random(32),
            'mode' => 'test',
            'eride_sandbox' => true,
        ];
        $agency->fapshi_ok_at = null;
        $agency->save();
        return $this->testFapshi($agency->fresh());
    }

    /**
     * A new agency goes live once it can take money (Fapshi tested) and has a departure open with a bus.
     * A paused agency stays paused: only its reason clearing (or an admin) brings it back.
     */
    public function refreshStatus(BusAgency $agency): BusAgency
    {
        if ($agency->status === BusAgency::SETUP && $agency->fapshi_ok_at
            && BusDeparture::where('agency_id', $agency->id)->whereNotNull('vehicle_id')->whereIn('status', BusDeparture::OPEN)
                ->where('date', '>=', today())->exists()) {
            $agency->update(['status' => BusAgency::LIVE]);
        }
        return $agency;
    }
}
