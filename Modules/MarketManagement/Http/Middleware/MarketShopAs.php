<?php

namespace Modules\MarketManagement\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\MarketManagement\Entities\MarketBooking;
use Modules\MarketManagement\Entities\MarketConversation;
use Modules\MarketManagement\Entities\MarketItem;
use Modules\MarketManagement\Entities\MarketOrder;
use Modules\MarketManagement\Entities\MarketShopStaff;
use Modules\MarketManagement\Entities\MarketZamaDeal;
use Modules\MarketManagement\Lib\ShopTeam;

/**
 * A staff member working for a shop (header X-Shop-As: the shop's id). The request then
 * acts as the shop's owner, but only on the shop's side and within the permissions the
 * owner gave; prices and the owner's own buying, chats and account stay out of reach.
 * Every change is written in the shop's activity log.
 */
class MarketShopAs
{
    public function handle(Request $request, Closure $next)
    {
        $shopId = $request->header('X-Shop-As');
        // team/mine is about the person themselves (the shops they work for), whatever shop they work as.
        if (!$shopId || str_starts_with($request->path(), 'api/customer/market/team/mine')) {
            return $next($request);
        }
        $user = $request->user();
        $staff = $user ? MarketShopStaff::with('shop.owner')->where('shop_id', $shopId)->where('user_id', $user->id)
            ->where('status', MarketShopStaff::ACTIVE)->first() : null;
        if (!$staff || !$staff->shop?->owner) {
            return $this->fail('team_gone', translate('You no longer work for this shop.'));
        }
        $uri = Str::after($request->route()->uri(), 'api/customer/market/');
        $params = $request->route()->parameters();
        if ($request->isMethod('GET')) {
            // Lists show the shop's side only.
            if ($uri === 'orders') {
                $request->query->set('role', 'selling');
            } elseif ($uri === 'bookings') {
                $request->query->set('role', 'provider');
            } elseif ($uri === 'zama/deals' && $request->query('as') === 'buyer') {
                return $this->fail('team_owner', translate('Only the shop owner can do this.'));
            }
        } else {
            $permission = ShopTeam::ROUTES[$uri] ?? null;
            if (!$permission) {
                return $this->fail('team_owner', translate('Only the shop owner can do this.'));
            }
            if (!$staff->can($permission)) {
                return $this->fail('team_permission', translate('The owner has not given you this.'));
            }
        }
        if (!ShopTeam::reaches($staff, $uri, $params)) {
            return $this->fail('team_owner', translate('Only the shop owner can see this.'));
        }
        if ($uri === 'items/{code}/update') {
            // Prices are the owner's: whatever staff send for them is ignored.
            foreach (ShopTeam::OWNER_ONLY_FIELDS as $field) {
                $request->request->remove($field);
                $request->query->remove($field);
                if ($request->isJson()) {
                    $request->json()->remove($field);
                }
            }
        }

        // From here, the request is the owner's.
        $owner = $staff->shop->owner;
        auth()->guard('api')->setUser($owner);
        $request->setUserResolver(fn() => $owner);
        $request->attributes->set('market_staff', $staff);

        $response = $next($request);

        if (!$request->isMethod('GET') && $response->getStatusCode() < 300 && $uri !== 'chats/{id}/messages') {
            ShopTeam::log($staff, str_replace(['/{id}', '/{code}', '/{messageId}', '/{action}', '/'], ['', '', '', '/' . ($params['action'] ?? ''), '.'], $uri),
                $this->subject($uri, $params));
        }
        return $response;
    }

    /** What the action was about, in words the owner recognises. */
    private function subject(string $uri, array $params): ?string
    {
        $id = $params['id'] ?? null;
        return match (true) {
            str_starts_with($uri, 'orders/') => ($o = MarketOrder::find($id)) ? '#' . $o->code : null,
            str_starts_with($uri, 'bookings/') => ($b = MarketBooking::find($id)) ? '#' . $b->code : null,
            str_starts_with($uri, 'items/') => MarketItem::where('code', $params['code'] ?? '')->value('title'),
            str_starts_with($uri, 'chats/') => MarketConversation::with('item')->find($id)?->item?->title,
            str_starts_with($uri, 'zama/deals/') => MarketZamaDeal::with('source')->find($id)?->source?->title,
            default => null,
        };
    }

    private function fail(string $code, string $message)
    {
        return response()->json(responseFormatter(DEFAULT_400, errors: [['error_code' => $code, 'message' => $message]]), 403);
    }
}
