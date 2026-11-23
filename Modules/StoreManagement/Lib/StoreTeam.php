<?php

namespace Modules\StoreManagement\Lib;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Modules\StoreManagement\Entities\Store;
use Modules\StoreManagement\Entities\StoreItem;
use Modules\StoreManagement\Entities\StoreOrder;
use Modules\StoreManagement\Entities\StoreStaff;
use Modules\UserManagement\Entities\User;
use Throwable;

/**
 * A partner's team in the portal: staff logins (their own email and password) with a role
 * (manager: everything staff can do; sales: orders, open or closed, what is available
 * today) and the permissions the owner gave.
 *
 * Always the owner's: prices (menu prices, minimum order, own delivery fee, reseller rate),
 * adding menu items, payout details and the team. Every change a staff member makes is logged.
 */
class StoreTeam
{
    public const PERMISSIONS = ['orders', 'open_close', 'availability', 'menu', 'settings', 'riders', 'earnings'];
    public const ROLES = [
        'manager' => self::PERMISSIONS,
        'sales' => ['orders', 'open_close', 'availability'],
    ];
    public const MAX = 15;

    /** Portal pages and actions => the permission they need (null: anyone on the team). Anything else is the owner's. */
    public const ROUTES = [
        'partner.orders' => 'orders', 'partner.orders.feed' => 'orders', 'partner.orders.history' => 'orders',
        'partner.orders.accept' => 'orders', 'partner.orders.reject' => 'orders', 'partner.orders.ready' => 'orders',
        'partner.orders.hand-over' => 'orders', 'partner.orders.out' => 'orders', 'partner.orders.use-rider' => 'orders',
        'partner.orders.open-riders' => 'orders', 'partner.orders.swaps' => 'orders', 'partner.orders.propose' => 'orders',
        'partner.menu' => ['menu', 'availability'],
        'partner.menu.category.save' => 'menu', 'partner.menu.category.delete' => 'menu',
        'partner.menu.item.save' => 'menu', 'partner.menu.item.delete' => 'menu',
        'partner.menu.item.availability' => 'availability',
        'partner.store' => 'settings', 'partner.store.update' => 'settings',
        'partner.store.open' => 'open_close',
        'partner.riders' => 'riders', 'partner.riders.add' => 'riders', 'partner.riders.mode' => 'riders', 'partner.riders.remove' => 'riders',
        'partner.earnings' => 'earnings',
        'partner.share' => null,
        'partner.share.look' => 'settings', 'partner.share.design' => 'settings', 'partner.share.design.remove' => 'settings',
    ];

    /** Store settings staff never send. */
    public const OWNER_ONLY_FIELDS = ['payout_method', 'payout_number', 'payout_name', 'reseller_pct', 'own_delivery_fee', 'min_order_amount'];

    public static function forUser(User $user): ?StoreStaff
    {
        return StoreStaff::with('store')->where('user_id', $user->id)->where('status', 'active')->first();
    }

    public static function members(Store $store): Collection
    {
        return StoreStaff::with('user')->where('store_id', $store->id)->where('status', 'active')->oldest()->get();
    }

    /** The owner gives someone a login. */
    public static function add(Store $store, array $data): StoreStaff
    {
        if (StoreStaff::where('store_id', $store->id)->where('status', 'active')->count() >= self::MAX) {
            self::fail('email', translate(key: 'A team can have up to {count} people.', replace: ['count' => self::MAX]));
        }
        $email = strtolower(trim($data['email']));
        if (User::where('email', $email)->exists()) {
            self::fail('email', translate('This email is already used on E-Ride. Use another one for this login.'));
        }
        $role = isset(self::ROLES[$data['role'] ?? '']) ? $data['role'] : 'sales';
        return DB::transaction(function () use ($store, $data, $email, $role) {
            $user = User::create(['first_name' => trim($data['name']), 'email' => $email, 'password' => Hash::make($data['password']),
                'user_type' => PARTNER_USER_TYPE, 'is_active' => 1]);
            $permissions = isset($data['permissions']) ? array_values(array_intersect(self::PERMISSIONS, (array)$data['permissions'])) : self::ROLES[$role];
            return StoreStaff::create(['store_id' => $store->id, 'user_id' => $user->id, 'role' => $role, 'permissions' => $permissions, 'status' => 'active']);
        });
    }

    public static function update(Store $store, string $staffId, string $role, array $permissions): StoreStaff
    {
        $row = self::row($store, $staffId);
        $row->update(['role' => isset(self::ROLES[$role]) ? $role : $row->role, 'permissions' => array_values(array_intersect(self::PERMISSIONS, $permissions))]);
        return $row->fresh('user');
    }

    public static function password(Store $store, string $staffId, string $password): void
    {
        self::row($store, $staffId)->user?->update(['password' => Hash::make($password)]);
    }

    /** Removed: the login stops working at once (switched off, kept for the history). */
    public static function remove(Store $store, string $staffId): void
    {
        $row = self::row($store, $staffId);
        DB::transaction(function () use ($row) {
            $row->update(['status' => 'removed', 'ended_at' => now()]);
            $row->user?->update(['is_active' => 0]);
        });
    }

    public static function activity(Store $store, int $limit = 60): Collection
    {
        $rows = DB::table('store_staff_activity')->where('store_id', $store->id)->orderByDesc('id')->limit($limit)->get();
        $names = User::whereIn('id', $rows->pluck('user_id')->unique())->pluck('first_name', 'id');
        return $rows->map(fn($r) => (object)['who' => $names[$r->user_id] ?? '', 'action' => $r->action, 'subject' => $r->subject,
            'at' => \Illuminate\Support\Carbon::parse($r->created_at)]);
    }

    // ------------------------------------------------------------ in the portal

    /** Whether this staff member may open this page or do this. */
    public static function allows(StoreStaff $staff, ?string $route): bool
    {
        if (!$route || !array_key_exists($route, self::ROUTES)) {
            return false;
        }
        $need = self::ROUTES[$route];
        if ($need === null) {
            return true;
        }
        foreach ((array)$need as $permission) {
            if ($staff->can($permission)) {
                return true;
            }
        }
        return false;
    }

    /** Takes out of a staff member's request what only the owner sets. Returns false when the action is the owner's. */
    public static function clean(StoreStaff $staff, Request $request): bool
    {
        $route = $request->route()?->getName();
        if ($route === 'partner.menu.item.save') {
            $itemId = $request->route('item');
            if (!$itemId) {
                // A new dish comes with its price: the owner adds it.
                return false;
            }
            $price = StoreItem::where('store_id', $staff->store_id)->whereKey($itemId)->value('price');
            if ($price === null) {
                return false;
            }
            $request->merge(['price' => $price]);
        }
        if ($route === 'partner.store.update') {
            foreach (self::OWNER_ONLY_FIELDS as $field) {
                $request->request->remove($field);
            }
        }
        return true;
    }

    public static function log(StoreStaff $staff, Request $request): void
    {
        try {
            $route = (string)$request->route()?->getName();
            $order = $request->route('order');
            $subject = match (true) {
                $order !== null => '#' . (($order instanceof StoreOrder ? $order : StoreOrder::find($order))?->ref_id ?? ''),
                $request->route('item') !== null => StoreItem::find($request->route('item'))?->name,
                default => null,
            };
            DB::table('store_staff_activity')->insert(['store_id' => $staff->store_id, 'user_id' => $staff->user_id,
                'action' => mb_substr(str_replace('partner.', '', $route), 0, 60), 'subject' => $subject ? mb_substr($subject, 0, 120) : null, 'created_at' => now()]);
        } catch (Throwable) {
        }
    }

    private static function row(Store $store, string $staffId): StoreStaff
    {
        return StoreStaff::with('user')->where('store_id', $store->id)->whereKey($staffId)->where('status', 'active')->first()
            ?? self::fail('staff', translate('This person is not on your team.'));
    }

    private static function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
