<?php

namespace Modules\MarketManagement\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\MarketManagement\Entities\MarketShopStaff;
use Modules\MarketManagement\Lib\ShopTeam;
use Modules\MarketManagement\Service\ShopService;

/**
 * The shop team. The owner: who is on it, invite by phone, role and permissions, remove,
 * and "who did what". A staff member: the shops they work for, answer an invitation,
 * on duty or not, leave.
 */
class MarketTeamController extends Controller
{
    public function __construct(protected ShopService $shops)
    {
    }

    // ------------------------------------------------------------ the owner

    public function index(Request $request): JsonResponse
    {
        if (ShopTeam::acting($request)) {
            return $this->fail('team_owner', translate('Only the shop owner can do this.'));
        }
        $shop = $this->shops->mine($request->user());
        if (!$shop) {
            return $this->fail('shop', translate('Open your shop first.'));
        }
        return response()->json(responseFormatter(DEFAULT_200, [
            'permissions' => ShopTeam::PERMISSIONS,
            'roles' => ShopTeam::ROLES,
            'max' => ShopTeam::MAX,
            'members' => ShopTeam::members($shop)->map(fn(MarketShopStaff $s) => $this->member($s))->all(),
            'activity' => ShopTeam::activity($shop)->all(),
        ]));
    }

    public function invite(Request $request): JsonResponse
    {
        $data = $request->validate(['phone' => 'required|string|max:20', 'role' => ['required', Rule::in(array_keys(ShopTeam::ROLES))]]);
        $shop = $this->shops->mine($request->user()) ?? abort(403);
        return response()->json(responseFormatter(DEFAULT_200, $this->member(ShopTeam::invite($shop, $data['phone'], $data['role']))));
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'role' => ['nullable', Rule::in(array_keys(ShopTeam::ROLES))],
            'permissions' => 'nullable|array',
            'permissions.*' => ['string', Rule::in(ShopTeam::PERMISSIONS)],
        ]);
        $shop = $this->shops->mine($request->user()) ?? abort(403);
        $permissions = $request->has('permissions') ? (array)($data['permissions'] ?? []) : null;
        return response()->json(responseFormatter(DEFAULT_200, $this->member(ShopTeam::update($shop, $id, $data['role'] ?? null, $permissions))));
    }

    public function remove(Request $request, string $id): JsonResponse
    {
        $shop = $this->shops->mine($request->user()) ?? abort(403);
        ShopTeam::remove($shop, $id);
        return response()->json(responseFormatter(DEFAULT_200, ['removed' => true]));
    }

    // ------------------------------------------------------------ the staff member

    public function mine(Request $request): JsonResponse
    {
        return response()->json(responseFormatter(DEFAULT_200, [
            'shops' => ShopTeam::shopsFor($request->user())->map(fn(MarketShopStaff $s) => [
                'id' => $s->id,
                'status' => $s->status,
                'role' => $s->role,
                'permissions' => array_values((array)$s->permissions),
                'on_duty' => (bool)$s->on_duty,
                'shop' => ['id' => $s->shop_id, 'slug' => $s->shop?->slug, 'name' => $s->shop?->name, 'logo' => $s->shop?->logoUrl()],
            ])->values()->all(),
        ]));
    }

    public function answer(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['accept' => 'required|boolean']);
        ShopTeam::answer($request->user(), $id, (bool)$data['accept']);
        return $this->mine($request);
    }

    public function duty(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['on' => 'required|boolean']);
        ShopTeam::duty($request->user(), $id, (bool)$data['on']);
        return $this->mine($request);
    }

    public function leave(Request $request, string $id): JsonResponse
    {
        ShopTeam::leave($request->user(), $id);
        return $this->mine($request);
    }

    private function member(MarketShopStaff $s): array
    {
        return [
            'id' => $s->id,
            'name' => trim((string)$s->user?->first_name . ' ' . (string)$s->user?->last_name),
            'phone' => $s->user?->phone,
            'status' => $s->status,
            'role' => $s->role,
            'permissions' => array_values((array)$s->permissions),
            'on_duty' => (bool)$s->on_duty,
            'since' => $s->accepted_at?->toIso8601String(),
        ];
    }

    private function fail(string $code, string $message): JsonResponse
    {
        return response()->json(responseFormatter(DEFAULT_400, errors: [['error_code' => $code, 'message' => $message]]), 403);
    }
}
