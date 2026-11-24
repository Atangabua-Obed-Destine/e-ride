<?php

namespace Modules\BusManagement\Service;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\BusManagement\Entities\BusAgency;
use Modules\BusManagement\Entities\BusCompany;
use Modules\BusManagement\Entities\BusCompanyStaff;
use Modules\BusManagement\Entities\BusStaff;
use Modules\BusManagement\Entities\BusVehicle;
use Modules\BusManagement\Lib\BusSettings;
use Modules\BusManagement\Lib\CameroonRegions;
use Modules\UserManagement\Entities\User;

/**
 * Bus companies: a head office over its branches. E-Ride creates the company and links verified agencies
 * (branches) to it; the head office watches every branch, and the director chooses where the money goes
 * and how E-Ride bills. Buses become the company's, shared by all branches.
 */
class CompanyService
{
    public function __construct(protected AgencyService $agencies)
    {
    }

    // ------------------------------------------------------------- E-Ride admin

    public function create(array $input): BusCompany
    {
        $data = Validator::make($input, [
            'name' => 'required|string|max:120',
            'about' => 'nullable|string|max:1000',
        ], [], ['name' => translate('name')])->validate();
        $base = Str::slug($data['name']) ?: 'bus-company';
        $slug = $base;
        for ($i = 2; BusCompany::where('slug', $slug)->exists(); $i++) {
            $slug = $base . '-' . $i;
        }
        return BusCompany::create([
            'name' => trim($data['name']),
            'slug' => $slug,
            'about' => $data['about'] ?? null,
            'status' => BusCompany::ACTIVE,
            'money_mode' => BusCompany::PER_BRANCH,
            'bill_mode' => BusCompany::PER_BRANCH,
            'webhook_code' => Str::lower(Str::random(16)),
        ]);
    }

    public function update(BusCompany $company, array $input): BusCompany
    {
        $data = Validator::make($input, [
            'name' => 'required|string|max:120',
            'about' => 'nullable|string|max:1000',
            'turnaround_minutes' => 'nullable|integer|min:0|max:720',
        ], [], ['name' => translate('name'), 'turnaround_minutes' => translate('turnaround time')])->validate();
        $company->update([
            'name' => trim($data['name']),
            'about' => $data['about'] ?? null,
            'turnaround_minutes' => (int)($data['turnaround_minutes'] ?? $company->turnaround_minutes),
        ]);
        return $company;
    }

    /** Makes an agency a branch of the company. Its buses join the company's shared fleet. */
    public function link(BusCompany $company, BusAgency $agency, ?string $branchLabel = null, ?string $region = null): BusAgency
    {
        if ($agency->company_id && (string)$agency->company_id !== (string)$company->id) {
            throw ValidationException::withMessages(['agency_id' => translate('This agency is already a branch of another company. Remove it there first.')]);
        }
        if ($region !== null && $region !== '' && !isset(CameroonRegions::REGIONS[$region])) {
            throw ValidationException::withMessages(['region' => translate('Choose a region from the list.')]);
        }
        // One plate once in a company's fleet.
        $plates = BusVehicle::where('agency_id', $agency->id)->pluck('registration');
        $taken = BusVehicle::where('company_id', $company->id)->where('agency_id', '!=', $agency->id)->whereIn('registration', $plates)->pluck('registration');
        if ($taken->isNotEmpty()) {
            throw ValidationException::withMessages(['agency_id' => translate(key: 'Another branch already has bus {plates}. Fix the registration first.', replace: ['plates' => $taken->implode(', ')])]);
        }
        return DB::transaction(function () use ($company, $agency, $branchLabel, $region) {
            $agency->update([
                'company_id' => $company->id,
                'branch_label' => filled($branchLabel) ? trim($branchLabel) : $agency->branch_label,
                'region' => filled($region) ? $region : ($agency->region ?: CameroonRegions::of($agency->origin_city)),
            ]);
            BusVehicle::where('agency_id', $agency->id)->update(['company_id' => $company->id]);
            return $agency->fresh();
        });
    }

    /** The agency stands alone again, with the buses whose home it is. */
    public function unlink(BusAgency $agency): BusAgency
    {
        return DB::transaction(function () use ($agency) {
            BusVehicle::where('agency_id', $agency->id)->update(['company_id' => null]);
            $agency->update(['company_id' => null]);
            return $agency->fresh();
        });
    }

    // ------------------------------------------------------------- who sees what

    /** The branches this head office person may see (a regional manager: only theirs). */
    public static function branchIds(BusCompanyStaff $staff): array
    {
        $all = BusAgency::where('company_id', $staff->company_id)->pluck('id')->map(fn($id) => (string)$id)->all();
        if ($staff->role !== BusCompanyStaff::REGIONAL) {
            return $all;
        }
        return array_values(array_intersect($all, array_map('strval', (array)$staff->agency_ids)));
    }

    public static function canSee(BusCompanyStaff $staff, BusAgency $agency): bool
    {
        return in_array((string)$agency->id, self::branchIds($staff), true);
    }

    // ------------------------------------------------------------- head office staff

    /** Adds a head office person (director, regional manager, accountant). Returns [staff, login]. */
    public function addStaff(BusCompany $company, array $input): array
    {
        $branchIds = BusAgency::where('company_id', $company->id)->pluck('id')->map(fn($id) => (string)$id)->all();
        $data = Validator::make($input, [
            'name' => 'required|string|max:80',
            'email' => 'nullable|email|max:120',
            'role' => ['required', Rule::in(BusCompanyStaff::ROLES)],
            'agency_ids' => 'nullable|array',
            'agency_ids.*' => [Rule::in($branchIds)],
            'password' => 'required|string|min:8|max:100',
        ], [], ['name' => translate('name'), 'password' => translate('password')])->validate();
        if ($data['role'] === BusCompanyStaff::REGIONAL && empty($data['agency_ids'])) {
            throw ValidationException::withMessages(['agency_ids' => translate('Choose the branches this regional manager looks after.')]);
        }
        $email = filled($data['email'] ?? null) ? strtolower(trim($data['email'])) : null;
        if ($email && User::where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => translate('This email already has an E-Ride account. Use another email, or leave it empty for a username.')]);
        }
        $login = $email ?? $this->agencies->freeUsername($data['name'], $company);
        return DB::transaction(function () use ($company, $data, $email, $login) {
            $user = User::create([
                'first_name' => trim($data['name']),
                'email' => $email ?? ($login . '@' . AgencyService::USERNAME_DOMAIN),
                'password' => Hash::make($data['password']),
                'user_type' => PARTNER_USER_TYPE,
                'is_active' => 1,
            ]);
            $staff = BusCompanyStaff::create([
                'company_id' => $company->id,
                'user_id' => $user->id,
                'role' => $data['role'],
                'agency_ids' => $data['role'] === BusCompanyStaff::REGIONAL ? array_values($data['agency_ids']) : null,
                'active' => true,
            ]);
            return [$staff, $login];
        });
    }

    public function setStaffActive(BusCompany $company, BusCompanyStaff $staff, bool $active, ?BusCompanyStaff $by = null): BusCompanyStaff
    {
        $this->ownStaff($company, $staff);
        if ($by && $staff->id === $by->id) {
            throw ValidationException::withMessages(['staff' => translate('You cannot switch off your own account.')]);
        }
        if (!$active && $staff->role === BusCompanyStaff::DIRECTOR
            && BusCompanyStaff::where('company_id', $company->id)->where('role', BusCompanyStaff::DIRECTOR)->where('active', true)->count() <= 1) {
            throw ValidationException::withMessages(['staff' => translate('The company needs at least one director.')]);
        }
        $staff->update(['active' => $active]);
        return $staff;
    }

    public function resetStaffPassword(BusCompany $company, BusCompanyStaff $staff, string $password): void
    {
        $this->ownStaff($company, $staff);
        if (mb_strlen($password) < 8) {
            throw ValidationException::withMessages(['password' => translate('Use at least 8 characters.')]);
        }
        $staff->user->update(['password' => Hash::make($password)]);
    }

    protected function ownStaff(BusCompany $company, BusCompanyStaff $staff): void
    {
        if ((string)$staff->company_id !== (string)$company->id) {
            abort(404);
        }
    }

    // ------------------------------------------------------------- money and bill

    /**
     * Where passengers' money goes and how E-Ride bills. Collecting into one company account needs that
     * account connected first, or no branch could take payment.
     */
    public function setModes(BusCompany $company, array $input): BusCompany
    {
        $data = Validator::make($input, [
            'money_mode' => ['required', Rule::in([BusCompany::PER_BRANCH, BusCompany::COMPANY])],
            'bill_mode' => ['required', Rule::in([BusCompany::PER_BRANCH, BusCompany::COMPANY])],
        ])->validate();
        if ($data['money_mode'] === BusCompany::COMPANY && !$company->fapshi_ok_at) {
            throw ValidationException::withMessages(['money_mode' => translate('Connect and test the company Fapshi account first. Until then, each branch keeps its own.')]);
        }
        if ($data['money_mode'] === BusCompany::PER_BRANCH && $company->collectsMoney()) {
            $missing = $company->branches()->where('status', BusAgency::LIVE)->whereNull('fapshi_ok_at')->get()->map->branchLabel();
            if ($missing->isNotEmpty()) {
                throw ValidationException::withMessages(['money_mode' => translate(key: 'These branches have no Fapshi account of their own, so they could not take payment: {branches}.',
                    replace: ['branches' => $missing->implode(', ')])]);
            }
        }
        $company->update($data);
        $company->branches()->get()->each(fn($b) => $this->agencies->refreshStatus($b));
        return $company;
    }

    // ------------------------------------------------------------- rules

    public function saveRulesTemplate(BusCompany $company, array $rules): BusCompany
    {
        $company->update(['rules_template' => BusSettings::rules($rules)]);
        return $company;
    }

    /** Copies the company's booking rules to these branches. Returns how many changed. */
    public function applyRules(BusCompany $company, array $agencyIds): int
    {
        $template = $company->rules_template;
        if (!$template) {
            throw ValidationException::withMessages(['rules' => translate('Save the company booking rules first.')]);
        }
        $n = 0;
        BusAgency::where('company_id', $company->id)->whereIn('id', $agencyIds)->get()->each(function (BusAgency $a) use ($template, &$n) {
            // Branch-only switches stay as the branch set them (its paper copy, its counter seats).
            $keep = array_intersect_key((array)$a->rules, array_flip(['counter_seats']));
            $a->update(['rules' => array_merge($template, $keep)]);
            $n++;
        });
        return $n;
    }

    /** Pauses or resumes a branch's app sales from the head office (the counter keeps selling). */
    public function pauseBranch(BusAgency $agency, string $userId, bool $pause): int
    {
        $paper = app(PaperService::class);
        if ($pause) {
            return $paper->pause($agency, $userId);
        }
        $paper->resume($agency);
        return 0;
    }
}
