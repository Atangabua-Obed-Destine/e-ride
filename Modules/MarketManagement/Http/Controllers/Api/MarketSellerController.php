<?php

namespace Modules\MarketManagement\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\MarketManagement\Entities\MarketItem;
use Modules\MarketManagement\Entities\MarketShop;
use Modules\MarketManagement\Lib\MarketSettings;
use Modules\MarketManagement\Service\ItemService;
use Modules\MarketManagement\Service\ShopService;
use Modules\MarketManagement\Transformers\MarketPresenter;

/** Customer app, E-Ride Market for sellers: my shop, my items, verification. */
class MarketSellerController extends Controller
{
    public function __construct(protected ShopService $shops, protected ItemService $items)
    {
    }

    public function myShop(Request $request): JsonResponse
    {
        $user = $request->user();
        $shop = $this->shops->mine($user);
        if (!$shop) {
            return response()->json(responseFormatter(DEFAULT_200, ['shop' => null, 'items' => [],
                'cannot_open_reason' => $this->shops->cannotOpenReason($user)]));
        }
        $items = MarketItem::with('photos')->where('shop_id', $shop->id)->where('status', '!=', MarketItem::REMOVED)->latest()->get();
        $shop->setRelation('items', $items);
        return response()->json(responseFormatter(DEFAULT_200, [
            'shop' => MarketPresenter::shop($shop, $user->id, true),
            'items' => $items->map(fn(MarketItem $i) => MarketPresenter::item($i->setRelation('shop', $shop), $user->id))->all(),
            'cannot_open_reason' => $this->shops->cannotOpenReason($user),
        ]));
    }

    /** Opens the rider's shop, or saves its details. */
    public function saveShop(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|min:3|max:80',
            'category' => ['nullable', Rule::in(MarketSettings::CATEGORIES)],
            'about' => 'nullable|string|max:600',
            'area_label' => 'nullable|string|max:80',
            'pickup_lat' => 'nullable|numeric|between:-90,90',
            'pickup_lng' => 'nullable|numeric|between:-180,180',
            'pickup_address' => 'nullable|string|max:191',
            'logo' => 'nullable|image|max:' . convertBytesToKiloBytes(maxUploadSize('image')),
            'agree_rules' => 'nullable|boolean',
        ]);
        if ($validator->fails()) {
            return response()->json(responseFormatter(DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        $user = $request->user();
        $existing = $this->shops->mine($user);
        if (!$existing && !$request->boolean('agree_rules')) {
            return $this->fail('agree_rules', translate('Agree to the E-Ride Market rules to open a shop.'));
        }
        $shop = $existing
            ? $this->shops->update($existing, $validator->validated(), $request->file('logo'))
            : $this->shops->open($user, $validator->validated(), $request->file('logo'));
        return response()->json(responseFormatter(DEFAULT_STORE_200, MarketPresenter::shop($shop, $user->id, true)));
    }

    /** How the shop takes orders (also the quick "Taking orders" switch). */
    public function orderSettings(Request $request): JsonResponse
    {
        $shop = $this->shops->mine($request->user());
        if (!$shop) {
            return $this->fail('shop', translate('Open your shop first.'));
        }
        $validator = Validator::make($request->all(), [
            'taking_orders' => 'nullable|boolean',
            'fulfilment' => 'nullable',
            'pay_methods' => 'nullable',
            'open_from' => 'nullable|string|max:5',
            'open_to' => 'nullable|string|max:5',
            'min_order' => 'nullable|numeric|min:0|max:100000000',
        ]);
        if ($validator->fails()) {
            return response()->json(responseFormatter(DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        $data = array_intersect_key($request->all(), array_flip(['taking_orders', 'fulfilment', 'pay_methods', 'open_from', 'open_to', 'min_order']));
        if (array_key_exists('taking_orders', $data)) {
            $data['taking_orders'] = $request->boolean('taking_orders');
        }
        $shop = $this->shops->saveOrderSettings($shop, $data);
        return response()->json(responseFormatter(DEFAULT_200, MarketPresenter::shop($shop, $request->user()->id, true)));
    }

    public function requestVerification(Request $request): JsonResponse
    {
        $shop = $this->shops->mine($request->user());
        if (!$shop) {
            return $this->fail('shop', translate('Open your shop first.'));
        }
        return response()->json(responseFormatter(DEFAULT_200, MarketPresenter::shop($this->shops->requestVerification($shop), $request->user()->id, true)));
    }

    public function storeItem(Request $request): JsonResponse
    {
        $shop = $this->shops->mine($request->user());
        if (!$shop) {
            return $this->fail('shop', translate('Open your shop first.'));
        }
        $validator = Validator::make($request->all(), $this->rules(true));
        if ($validator->fails()) {
            return response()->json(responseFormatter(DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        $item = $this->items->create($shop, $validator->validated(), $request->file('photos', []), $request->file('previews', []));
        return response()->json(responseFormatter(DEFAULT_STORE_200, MarketPresenter::item($item, $request->user()->id, true)));
    }

    public function updateItem(Request $request, string $code): JsonResponse
    {
        $item = $this->ownItem($request, $code);
        if ($item instanceof JsonResponse) {
            return $item;
        }
        $validator = Validator::make($request->all(), $this->rules(false));
        if ($validator->fails()) {
            return response()->json(responseFormatter(DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        $item = $this->items->update($item, $validator->validated(), $request->file('photos', []),
            array_map('intval', (array)$request->input('remove_photo_ids', [])), $request->file('previews', []));
        return response()->json(responseFormatter(DEFAULT_200, MarketPresenter::item($item, $request->user()->id, true)));
    }

    public function soldOut(Request $request, string $code): JsonResponse
    {
        $item = $this->ownItem($request, $code);
        if ($item instanceof JsonResponse) {
            return $item;
        }
        $item = $this->items->setSoldOut($item, $request->boolean('sold_out', true));
        return response()->json(responseFormatter(DEFAULT_200, MarketPresenter::item($item, $request->user()->id, true)));
    }

    public function destroyItem(Request $request, string $code): JsonResponse
    {
        $item = $this->ownItem($request, $code);
        if ($item instanceof JsonResponse) {
            return $item;
        }
        $this->items->delete($item);
        return response()->json(responseFormatter(DEFAULT_200, ['deleted' => true]));
    }

    private function ownItem(Request $request, string $code): MarketItem|JsonResponse
    {
        $item = MarketItem::with(['photos', 'shop'])->where('code', $code)->first();
        if (!$item || $item->shop?->user_id !== $request->user()->id) {
            return $this->fail('item', translate('This item is not yours.'));
        }
        return $item;
    }

    private function rules(bool $creating): array
    {
        $image = 'image|max:' . convertBytesToKiloBytes(maxUploadSize('image'));
        return [
            'title' => ($creating ? 'required' : 'sometimes') . '|string|min:3|max:120',
            'description' => 'nullable|string|max:3000',
            'price' => 'nullable|numeric|min:0|max:100000000',
            'negotiable' => 'nullable|boolean',
            'condition' => [$creating ? 'required' : 'sometimes', Rule::in(MarketItem::CONDITIONS)],
            'digital_kind' => ['nullable', Rule::in(MarketSettings::DIGITAL_KINDS)],
            'category' => ['nullable', Rule::in(MarketSettings::CATEGORIES)],
            'quantity' => 'nullable|integer|min:0|max:100000',
            'pickup' => 'nullable|boolean',
            'eride_delivery' => 'nullable|boolean',
            'seller_delivers' => 'nullable|boolean',
            'owns_rights' => 'nullable|boolean',
            'photos' => 'nullable|array|max:' . MarketItem::MAX_PHOTOS,
            'photos.*' => $image,
            'previews' => 'nullable|array|max:' . MarketItem::MAX_PREVIEWS,
            'previews.*' => $image,
            'remove_photo_ids' => 'nullable|array',
            // {"colours":[{"name":"Purple","photo_id":12} or {"name":"Red","photo_index":0}],"sizes":["40","41"]}
            'options' => 'nullable|string|max:6000',
            // {"Purple|40": 2, ...}
            'stock' => 'nullable|string|max:20000',
            'wholesale_price' => 'nullable|numeric|min:0|max:100000000',
            'wholesale_min' => 'nullable|integer|min:0|max:100000',
        ];
    }

    private function fail(string $code, string $message): JsonResponse
    {
        return response()->json(responseFormatter(DEFAULT_400, errors: [['error_code' => $code, 'message' => $message]]), 403);
    }
}
