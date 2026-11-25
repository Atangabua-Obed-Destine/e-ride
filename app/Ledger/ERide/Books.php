<?php

namespace App\Ledger\ERide;

use App\Ledger\Permissions;
use Illuminate\Support\Facades\DB;
use Modules\BusManagement\Entities\BusAgency;
use Modules\BusManagement\Entities\BusCompany;
use Modules\BusManagement\Entities\BusCompanyStaff;
use Modules\BusManagement\Entities\BusStaff;

/**
 * E-Ride's side of the ledger: whose books are open and what the signed-in
 * person may do in them. (posinnova reads this from its business and its roles;
 * everything else in App\Ledger is posinnova's code, kept as close to it as
 * possible so fixes can be copied across.)
 *
 * A set of books (a "business" to the ledger) belongs to a bus branch or to a
 * bus company's head office: one row of ledger_businesses each. Someone opens
 * them from their portal (Accounting in the menu), which remembers the owner in
 * the session; every accounting request then checks they still work there.
 *
 * Roles (paxhitestbed's permission names, granted by the bus roles):
 *  - branch manager, head office director: everything;
 *  - branch accountant, head office accountant: everything except reopening a
 *    month or a year and approving a year-end closing or a budget (the user's
 *    rule, 2026-11-25: only the manager reopens).
 */
class Books
{
    public const AGENCY = 'bus_agency';
    public const COMPANY = 'bus_company';

    /** What only the manager or director may do. */
    public const OWNER_ONLY = ['accounting-period-reopen', 'year-end-closing-approve', 'budget-approve',
        // payroll: approving a payroll (when the books require it) and the payroll settings are the manager's
        'payroll-approve', 'payroll-setting'];

    /** The current request's books: ['business_id', 'type', 'owner', 'role', 'name', 'portal']. */
    protected static ?array $current = null;

    /**
     * Open the books of $owner for $user (from their portal): checks the role and
     * remembers the choice. Null when this person cannot see these books.
     */
    public static function open(object $user, string $type, string $ownerId): ?array
    {
        $books = self::resolve($user, $type, $ownerId);
        if ($books) {
            session()->put('ledger.owner', ['type' => $type, 'id' => $ownerId]);
        }

        return $books;
    }

    /** The books remembered in the session, checked again for this request. */
    public static function fromSession(object $user): ?array
    {
        $o = session('ledger.owner');
        if (! is_array($o) || empty($o['type']) || empty($o['id'])) {
            return null;
        }

        return self::resolve($user, $o['type'], $o['id']);
    }

    /** The books and this person's role there, or null. */
    public static function resolve(object $user, string $type, string $ownerId): ?array
    {
        if ($type === self::AGENCY) {
            $staff = BusStaff::where('user_id', $user->id)->where('agency_id', $ownerId)->where('active', true)->first();
            $agency = $staff?->agency;
            if (! $agency || $agency->status === BusAgency::CHECKING || ! in_array($staff->role, [BusStaff::MANAGER, BusStaff::ACCOUNTANT], true)) {
                return null;
            }

            return ['type' => $type, 'owner' => $agency, 'role' => $staff->role === BusStaff::MANAGER ? 'owner' : 'accountant',
                'name' => $agency->displayName(), 'portal' => 'bus-partner'] + ['business_id' => self::businessId($type, $agency->id, $agency->displayName())];
        }
        if ($type === self::COMPANY) {
            $staff = BusCompanyStaff::where('user_id', $user->id)->where('company_id', $ownerId)->where('active', true)->first();
            $company = $staff ? BusCompany::find($ownerId) : null;
            if (! $company || ! in_array($staff->role, [BusCompanyStaff::DIRECTOR, BusCompanyStaff::ACCOUNTANT], true)) {
                return null;
            }

            return ['type' => $type, 'owner' => $company, 'role' => $staff->role === BusCompanyStaff::DIRECTOR ? 'owner' : 'accountant',
                'name' => $company->name . ' · ' . translate('Head office'), 'portal' => 'bus-company'] + ['business_id' => self::businessId($type, $company->id, $company->name)];
        }

        return null;
    }

    /** The ledger_businesses row of an owner (made on first use; the name follows the owner's). */
    public static function businessId(string $type, string $ownerId, string $name): int
    {
        $row = DB::table('ledger_businesses')->where('owner_type', $type)->where('owner_id', $ownerId)->first();
        if (! $row) {
            DB::table('ledger_businesses')->insertOrIgnore(['owner_type' => $type, 'owner_id' => $ownerId, 'name' => $name,
                'enabled_modules' => json_encode(['accounting']), 'created_at' => now(), 'updated_at' => now()]);
            $row = DB::table('ledger_businesses')->where('owner_type', $type)->where('owner_id', $ownerId)->first();
        } elseif ($row->name !== $name) {
            DB::table('ledger_businesses')->where('id', $row->id)->update(['name' => $name, 'updated_at' => now()]);
        }

        return (int) $row->id;
    }

    /** The owner (BusAgency or BusCompany) of a set of books. */
    public static function owner(int $businessId): BusAgency|BusCompany|null
    {
        $row = DB::table('ledger_businesses')->where('id', $businessId)->first();
        if (! $row) {
            return null;
        }

        return $row->owner_type === self::AGENCY ? BusAgency::find($row->owner_id) : BusCompany::find($row->owner_id);
    }

    /** Make $books the current request's books (and what the posinnova screens read from the session). */
    public static function use(array $books): void
    {
        self::$current = $books;
        session()->put('user.business_id', $books['business_id']);
        session()->put('business', (object) ['id' => $books['business_id'], 'name' => $books['name']]);
        session()->put('currency', ['symbol' => 'FCFA', 'code' => 'XAF', 'thousand_separator' => ' ', 'decimal_separator' => ',']);
    }

    public static function current(): ?array
    {
        return self::$current;
    }

    /**
     * The buses of the open books' owner, for a fixed asset that is a bus: a branch's own buses
     * (and its company's shared ones), or all of a company's buses. [id => "registration · name"]
     */
    public static function fleet(): array
    {
        $owner = self::$current['owner'] ?? null;
        if (! $owner) {
            return [];
        }
        $q = \Modules\BusManagement\Entities\BusVehicle::query()->where('status', '!=', 'retired');
        if ($owner instanceof BusCompany) {
            $q->where('company_id', $owner->id);
        } else {
            $q->where(fn ($w) => $w->where('agency_id', $owner->id)->when($owner->company_id, fn ($x) => $x->orWhere('company_id', $owner->company_id)));
        }

        return $q->orderBy('registration')->get()->mapWithKeys(fn ($v) => [(string) $v->id => trim($v->registration.' · '.$v->name, ' ·')])->all();
    }

    /** Gate answer for a ledger permission (null: not a ledger permission, let the Gate decide). */
    public static function allows(string $ability): ?bool
    {
        if (! in_array($ability, Permissions::all(), true)) {
            return null;
        }
        $books = self::$current;
        if (! $books) {
            return false;
        }

        return $books['role'] === 'owner' || ! in_array($ability, self::OWNER_ONLY, true);
    }
}
