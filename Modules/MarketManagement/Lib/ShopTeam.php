<?php

namespace Modules\MarketManagement\Lib;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\MarketManagement\Entities\MarketBooking;
use Modules\MarketManagement\Entities\MarketConversation;
use Modules\MarketManagement\Entities\MarketMessage;
use Modules\MarketManagement\Entities\MarketOrder;
use Modules\MarketManagement\Entities\MarketShop;
use Modules\MarketManagement\Entities\MarketShopStaff;
use Modules\MarketManagement\Entities\MarketZamaDeal;
use Modules\UserManagement\Entities\User;
use Throwable;

/**
 * A Market shop's team: people the owner lets work for the shop with their own E-Ride
 * account. Roles are starting points (manager: everything staff can do; sales: chats,
 * orders, bookings, stock, zama); the owner can give or take away each permission.
 *
 * Always the owner's alone: prices, posting and deleting items, the shop's details,
 * verification, closing the shop, reselling other shops' items and the team itself.
 *
 * A staff member works "as the shop": the app sends X-Shop-As, and MarketShopAs lets
 * the request act for the owner only on the shop's side, within the permissions.
 */
class ShopTeam
{
    public const PERMISSIONS = ['chats', 'orders', 'bookings', 'stock', 'items', 'zama', 'settings', 'stats'];
    public const ROLES = [
        'manager' => self::PERMISSIONS,
        'sales' => ['chats', 'orders', 'bookings', 'stock', 'zama'],
    ];
    public const MAX = 10;

    /**
     * What staff may do as the shop: route (after customer/market/) => permission.
     * Anything sent (POST) that is not here stays with the owner.
     */
    public const ROUTES = [
        'chats/{id}/messages' => 'chats',
        'chats/{id}/block-buyer' => 'chats',
        'chats/{id}/quote' => 'chats',
        'orders/{id}/respond' => 'orders',
        'orders/{id}/decline' => 'orders',
        'orders/{id}/cancel' => 'orders',
        'orders/{id}/ready' => 'orders',
        'orders/{id}/trip' => 'orders',
        'orders/{id}/complete' => 'orders',
        'orders/{id}/payment-arranged' => 'orders',
        'orders/{id}/no-show' => 'orders',
        'items/{code}/auction/offer-next' => 'orders',
        'items/{code}/auction/answer' => 'orders',
        'bookings/{id}/accept' => 'bookings',
        'bookings/{id}/decline' => 'bookings',
        'bookings/{id}/suggest' => 'bookings',
        'bookings/{id}/send-quote' => 'bookings',
        'bookings/{id}/reschedule' => 'bookings',
        'bookings/{id}/cancel' => 'bookings',
        'bookings/{id}/no-show' => 'bookings',
        'bookings/{id}/complete' => 'bookings',
        'items/{code}/sold-out' => 'stock',
        'items/{code}/update' => 'items',
        'zama/deals/{id}/{action}' => 'zama',
        'zama/deals/{id}/sold' => 'zama',
        'zama/quotes/{messageId}/deal' => 'zama',
        'shop/order-settings' => 'settings',
        'shop/service-settings' => 'settings',
    ];

    /** Item fields staff never send: the owner sets prices (and what zama resellers pay). */
    public const OWNER_ONLY_FIELDS = ['price', 'negotiable', 'sale_type', 'wholesale_price', 'wholesale_min', 'price_type', 'extras',
        'zama_open', 'zama_price', 'zama_only', 'auction_start', 'auction_step', 'auction_days', 'auction_hours', 'auction_reserve'];

    // ============================================================ the owner

    public static function members(MarketShop $shop): Collection
    {
        return MarketShopStaff::with('user')->where('shop_id', $shop->id)->whereIn('status', [MarketShopStaff::INVITED, MarketShopStaff::ACTIVE])
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")->oldest()->get();
    }

    /** Invites someone by their E-Ride phone number. */
    public static function invite(MarketShop $shop, string $phone, string $role): MarketShopStaff
    {
        $role = isset(self::ROLES[$role]) ? $role : 'sales';
        $digits = preg_replace('/\D+/', '', $phone);
        $forms = array_values(array_unique(array_filter([$digits !== '' ? '+' . $digits : null, strlen($digits) === 9 ? '+237' . $digits : null, $digits])));
        $user = $forms ? User::where('user_type', 'customer')->where('is_active', 1)->whereIn('phone', $forms)->first() : null;
        if (!$user) {
            self::fail('phone', translate('Nobody uses E-Ride with this number. Ask them to sign up first, with this number.'));
        }
        if ($user->id === $shop->user_id) {
            self::fail('phone', translate('This is your own number.'));
        }
        $count = MarketShopStaff::where('shop_id', $shop->id)->whereIn('status', [MarketShopStaff::INVITED, MarketShopStaff::ACTIVE])->count();
        $row = MarketShopStaff::where('shop_id', $shop->id)->where('user_id', $user->id)->first();
        if ($row && in_array($row->status, [MarketShopStaff::INVITED, MarketShopStaff::ACTIVE], true)) {
            self::fail('phone', translate('This person is already on your team.'));
        }
        if ($count >= self::MAX) {
            self::fail('phone', translate(key: 'A team can have up to {count} people.', replace: ['count' => self::MAX]));
        }
        $row ??= new MarketShopStaff(['shop_id' => $shop->id, 'user_id' => $user->id]);
        $row->fill(['role' => $role, 'permissions' => self::ROLES[$role], 'status' => MarketShopStaff::INVITED, 'on_duty' => true, 'accepted_at' => null, 'ended_at' => null])->save();
        MarketPush::send($user, 'market_team_invite', '{shop} wants you on their team', 'Open E-Ride Market to accept, and help run the shop.',
            ['shop' => $shop->name], ['shop_slug' => $shop->slug]);
        return $row->fresh('user');
    }

    /** The owner changes someone's role and permissions (a role resets them to its defaults unless permissions are given). */
    public static function update(MarketShop $shop, string $staffId, ?string $role, ?array $permissions): MarketShopStaff
    {
        $row = MarketShopStaff::where('shop_id', $shop->id)->whereKey($staffId)->whereIn('status', [MarketShopStaff::INVITED, MarketShopStaff::ACTIVE])->first()
            ?? self::fail('staff', translate('This person is not on your team.'));
        if ($role !== null && isset(self::ROLES[$role])) {
            $row->role = $role;
            $row->permissions = self::ROLES[$role];
        }
        if ($permissions !== null) {
            $row->permissions = array_values(array_intersect(self::PERMISSIONS, $permissions));
        }
        $row->save();
        return $row->fresh('user');
    }

    public static function remove(MarketShop $shop, string $staffId): void
    {
        $row = MarketShopStaff::where('shop_id', $shop->id)->whereKey($staffId)->whereIn('status', [MarketShopStaff::INVITED, MarketShopStaff::ACTIVE])->first();
        if (!$row) {
            return;
        }
        $wasActive = $row->isActive();
        $row->update(['status' => 'removed', 'ended_at' => now()]);
        if ($wasActive) {
            MarketPush::send($row->user, 'market_team_removed', 'You no longer work for {shop}', 'The owner took you off the team.', ['shop' => $shop->name]);
        }
    }

    /** "Who did what", newest first. */
    public static function activity(MarketShop $shop, int $limit = 60): Collection
    {
        $rows = DB::table('market_shop_activity')->where('shop_id', $shop->id)->orderByDesc('id')->limit($limit)->get();
        $names = User::whereIn('id', $rows->pluck('user_id')->unique())->get(['id', 'first_name', 'last_name'])
            ->mapWithKeys(fn($u) => [(string)$u->id => trim($u->first_name . ' ' . $u->last_name)]);
        return $rows->map(fn($r) => ['who' => $names[$r->user_id] ?? '', 'action' => $r->action, 'subject' => $r->subject, 'at' => \Illuminate\Support\Carbon::parse($r->created_at)->toIso8601String()]);
    }

    // ============================================================ the staff member

    /** The shops someone works for or is invited to. */
    public static function shopsFor(User $user): Collection
    {
        return MarketShopStaff::with('shop')->where('user_id', $user->id)->whereIn('status', [MarketShopStaff::INVITED, MarketShopStaff::ACTIVE])
            ->whereHas('shop', fn($q) => $q->where('status', '!=', 'closed'))->get();
    }

    public static function answer(User $user, string $staffId, bool $accept): MarketShopStaff
    {
        $row = MarketShopStaff::with('shop.owner')->where('user_id', $user->id)->whereKey($staffId)->where('status', MarketShopStaff::INVITED)->first()
            ?? self::fail('staff', translate('This invitation is no longer open.'));
        $row->update($accept ? ['status' => MarketShopStaff::ACTIVE, 'accepted_at' => now()] : ['status' => 'declined', 'ended_at' => now()]);
        MarketPush::send($row->shop?->owner, $accept ? 'market_team_joined' : 'market_team_declined',
            $accept ? '{name} joined your team' : '{name} declined your invitation', $accept ? 'They can now help run {shop}.' : 'You can invite someone else.',
            ['name' => trim((string)$user->first_name) ?: translate('Someone'), 'shop' => $row->shop?->name]);
        return $row->fresh('shop');
    }

    public static function leave(User $user, string $staffId): void
    {
        $row = MarketShopStaff::with('shop.owner')->where('user_id', $user->id)->whereKey($staffId)->where('status', MarketShopStaff::ACTIVE)->first();
        if ($row) {
            $row->update(['status' => 'left', 'ended_at' => now()]);
            MarketPush::send($row->shop?->owner, 'market_team_left', '{name} left your team', 'They no longer work for {shop}.',
                ['name' => trim((string)$user->first_name) ?: translate('Someone'), 'shop' => $row->shop?->name]);
        }
    }

    public static function duty(User $user, string $staffId, bool $on): MarketShopStaff
    {
        $row = MarketShopStaff::where('user_id', $user->id)->whereKey($staffId)->where('status', MarketShopStaff::ACTIVE)->first()
            ?? self::fail('staff', translate('You no longer work for this shop.'));
        $row->update(['on_duty' => $on]);
        return $row->fresh('shop');
    }

    // ============================================================ working as the shop

    /** The staff row of the person working as the shop in this request, or null. */
    public static function acting(Request $request): ?MarketShopStaff
    {
        $row = $request->attributes->get('market_staff');
        return $row instanceof MarketShopStaff ? $row : null;
    }

    /**
     * Whether a staff member may reach this chat, order, booking or deal: only the shop's
     * side, never the owner's own buying.
     */
    public static function reaches(MarketShopStaff $staff, string $uri, array $params): bool
    {
        $ownerId = $staff->shop->user_id;
        $id = $params['id'] ?? null;
        if (str_starts_with($uri, 'chats/{id}')) {
            $chat = MarketConversation::find($id);
            return $chat && (($chat->seller_id === $ownerId && $chat->shop_id === $staff->shop_id && $staff->can('chats'))
                || ($chat->zama && $chat->buyer_id === $ownerId && $staff->can('zama')));
        }
        if (str_starts_with($uri, 'orders/{id}')) {
            $order = MarketOrder::find($id);
            return $order && $order->seller_id === $ownerId && $staff->can('orders');
        }
        if (str_starts_with($uri, 'bookings/{id}')) {
            $booking = MarketBooking::find($id);
            return $booking && $booking->provider_id === $ownerId && $staff->can('bookings');
        }
        if (str_starts_with($uri, 'zama/deals/{id}')) {
            $deal = MarketZamaDeal::find($id);
            return $deal && ($deal->owner_id === $ownerId || $deal->reseller_id === $ownerId) && $staff->can('zama');
        }
        if (str_starts_with($uri, 'zama/quotes/{messageId}')) {
            $chat = MarketMessage::find($params['messageId'] ?? null)?->conversation;
            return $chat && $chat->seller_id === $ownerId && $staff->can('zama');
        }
        return true;
    }

    public static function log(MarketShopStaff $staff, string $action, ?string $subject): void
    {
        try {
            DB::table('market_shop_activity')->insert(['shop_id' => $staff->shop_id, 'user_id' => $staff->user_id, 'action' => mb_substr($action, 0, 60),
                'subject' => $subject ? mb_substr($subject, 0, 120) : null, 'created_at' => now()]);
        } catch (Throwable) {
        }
    }

    /**
     * A push to a shop owner about the shop's business (a buyer's message, an order, a
     * booking, a zama deal): the on-duty staff allowed to handle it get it too, with the
     * shop to work as. Returns them.
     */
    public static function alsoNotify(User $owner, array $data): Collection
    {
        $permission = null;
        $shopId = null;
        try {
            if (!empty($data['conversation_id']) && ($chat = MarketConversation::find($data['conversation_id']))) {
                if ($chat->seller_id === $owner->id) {
                    [$permission, $shopId] = ['chats', $chat->shop_id];
                } elseif ($chat->zama && $chat->buyer_id === $owner->id) {
                    $permission = 'zama';
                }
            } elseif (!empty($data['order_id']) && ($order = MarketOrder::find($data['order_id'])) && $order->seller_id === $owner->id) {
                [$permission, $shopId] = ['orders', $order->shop_id];
            } elseif (!empty($data['booking_id']) && ($booking = MarketBooking::find($data['booking_id'])) && $booking->provider_id === $owner->id) {
                [$permission, $shopId] = ['bookings', $booking->shop_id];
            } elseif (!empty($data['zama_deal_id']) && ($deal = MarketZamaDeal::find($data['zama_deal_id']))
                && ($deal->owner_id === $owner->id || $deal->reseller_id === $owner->id)) {
                $permission = 'zama';
            }
            if (!$permission) {
                return collect();
            }
            $shopId ??= MarketShop::where('user_id', $owner->id)->value('id');
            if (!$shopId) {
                return collect();
            }
            return MarketShopStaff::with('user')->where('shop_id', $shopId)->where('status', MarketShopStaff::ACTIVE)->where('on_duty', true)->get()
                ->filter(fn(MarketShopStaff $s) => $s->can($permission) && $s->user)->values();
        } catch (Throwable) {
            return collect();
        }
    }

    private static function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
