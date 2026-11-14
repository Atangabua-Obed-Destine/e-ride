<?php

namespace Modules\MarketManagement\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Modules\MarketManagement\Entities\MarketOrder;
use Modules\MarketManagement\Entities\MarketReview;
use Modules\MarketManagement\Entities\MarketShop;
use Modules\MarketManagement\Lib\MarketSettings;
use Modules\MarketManagement\Service\MarketOrderService;
use Modules\MarketManagement\Transformers\MarketPresenter;

/** Customer app, E-Ride Market orders: for the buyer and for the seller. */
class MarketOrderController extends Controller
{
    public function __construct(protected MarketOrderService $orders)
    {
    }

    /** The buyer sends their cart for one shop. */
    public function place(Request $request): JsonResponse
    {
        if (!MarketSettings::enabled()) {
            return $this->fail('market', translate('E-Ride Market is not available right now.'));
        }
        $validator = Validator::make($request->all(), [
            'shop' => 'required|string|max:90',
            // [{"item":"vazerzga","colour":"Purple","size":"42","qty":2}]
            'lines' => 'required',
            'fulfilment' => ['required', Rule::in(MarketOrder::FULFILMENT)],
            'pay_method' => ['required', Rule::in(MarketOrder::PAY_METHODS)],
            'drop_lat' => 'nullable|numeric|between:-90,90',
            'drop_lng' => 'nullable|numeric|between:-180,180',
            'drop_address' => 'nullable|string|max:191',
            'note' => 'nullable|string|max:500',
        ]);
        if ($validator->fails()) {
            return response()->json(responseFormatter(DEFAULT_400, errors: errorProcessor($validator)), 403);
        }
        $shop = MarketShop::with('owner')->where('slug', $request->input('shop'))->first();
        if (!$shop) {
            return $this->fail('shop', translate('This shop is not available.'));
        }
        $lines = $request->input('lines');
        $lines = is_array($lines) ? $lines : (json_decode((string)$lines, true) ?: []);
        $order = $this->orders->place($request->user(), $shop, $lines, $request->only(['fulfilment', 'pay_method', 'drop_lat', 'drop_lng', 'drop_address', 'note']));
        return response()->json(responseFormatter(DEFAULT_STORE_200, MarketPresenter::order($order, $request->user()->id, true)));
    }

    /** ?role=buying|selling&tab=new|agree|active|confirmed|done */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'role' => 'nullable|in:buying,selling',
            'tab' => 'nullable|in:new,agree,active,confirmed,done',
            'limit' => 'nullable|integer|min:1|max:50',
            'offset' => 'nullable|integer|min:1',
        ]);
        $limit = (int)$request->query('limit', 20);
        $offset = ((int)$request->query('offset', 1) - 1) * $limit;
        [$total, $orders] = $this->orders->listFor($request->user(), (string)$request->query('role', 'buying'), (string)$request->query('tab', 'active'), $offset, $limit);
        return response()->json(responseFormatter(DEFAULT_200, [
            'total' => $total,
            'orders' => $orders->map(fn(MarketOrder $o) => MarketPresenter::order($o, $request->user()->id))->all(),
            'counts' => $this->orders->counts($request->user()),
        ]));
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $order = $this->mine($request, $id);
        return $order instanceof JsonResponse ? $order : $this->ok($request, $order);
    }

    public function respond(Request $request, string $id): JsonResponse
    {
        $order = $this->mine($request, $id);
        if ($order instanceof JsonResponse) {
            return $order;
        }
        // [{"id": 12, "qty": 1, "note": "Only one left"}]
        $raw = $request->input('lines');
        $raw = is_array($raw) ? $raw : (json_decode((string)$raw, true) ?: []);
        $answers = [];
        foreach ($raw as $row) {
            if (isset($row['id'])) {
                $answers[(int)$row['id']] = ['qty' => (int)($row['qty'] ?? 0), 'note' => $row['note'] ?? null];
            }
        }
        return $this->ok($request, $this->orders->respond($request->user(), $order, $answers));
    }

    public function decline(Request $request, string $id): JsonResponse
    {
        $order = $this->mine($request, $id);
        if ($order instanceof JsonResponse) {
            return $order;
        }
        $request->validate(['reason' => ['required', Rule::in(MarketOrder::DECLINE_REASONS)], 'note' => 'nullable|string|max:300', 'sold_out' => 'nullable|boolean']);
        return $this->ok($request, $this->orders->decline($request->user(), $order, $request->input('reason'), $request->input('note'), $request->boolean('sold_out')));
    }

    public function agree(Request $request, string $id): JsonResponse
    {
        $order = $this->mine($request, $id);
        return $order instanceof JsonResponse ? $order : $this->ok($request, $this->orders->agree($request->user(), $order));
    }

    public function cancel(Request $request, string $id): JsonResponse
    {
        $order = $this->mine($request, $id);
        if ($order instanceof JsonResponse) {
            return $order;
        }
        $request->validate(['reason' => 'nullable|string|max:300']);
        return $this->ok($request, $this->orders->cancel($request->user(), $order, $request->input('reason')));
    }

    public function ready(Request $request, string $id): JsonResponse
    {
        $order = $this->mine($request, $id);
        return $order instanceof JsonResponse ? $order : $this->ok($request, $this->orders->ready($request->user(), $order));
    }

    public function trip(Request $request, string $id): JsonResponse
    {
        $order = $this->mine($request, $id);
        if ($order instanceof JsonResponse) {
            return $order;
        }
        $request->validate(['trip_id' => 'required|string|max:40']);
        return $this->ok($request, $this->orders->attachTrip($request->user(), $order, $request->input('trip_id')));
    }

    public function complete(Request $request, string $id): JsonResponse
    {
        $order = $this->mine($request, $id);
        if ($order instanceof JsonResponse) {
            return $order;
        }
        $request->validate(['code' => 'required|string|max:8']);
        return $this->ok($request, $this->orders->complete($request->user(), $order, (string)$request->input('code')));
    }

    public function review(Request $request, string $id): JsonResponse
    {
        $order = $this->mine($request, $id);
        if ($order instanceof JsonResponse) {
            return $order;
        }
        $request->validate(['stars' => 'required|integer|min:1|max:5', 'tags' => 'nullable', 'comment' => 'nullable|string|max:500']);
        $tags = $request->input('tags', []);
        $tags = is_array($tags) ? $tags : (json_decode((string)$tags, true) ?: array_filter(explode(',', (string)$tags)));
        $this->orders->review($request->user(), $order, (int)$request->input('stars'), $tags, $request->input('comment'));
        return $this->ok($request, $order->fresh(['lines', 'shop']));
    }

    /** A shop's ratings, newest first. */
    public function reviews(Request $request, string $slug): JsonResponse
    {
        $shop = MarketShop::where('slug', $slug)->first();
        if (!$shop) {
            return $this->fail('shop', translate('This shop is not available.'));
        }
        $reviews = MarketReview::with('buyer')->where('shop_id', $shop->id)->where('hidden', false)->latest()->limit(50)->get();
        return response()->json(responseFormatter(DEFAULT_200, [
            'rating' => $shop->rating, 'rating_count' => (int)$shop->rating_count,
            'reviews' => $reviews->map(fn(MarketReview $r) => MarketPresenter::review($r))->all(),
        ]));
    }

    private function mine(Request $request, string $id): MarketOrder|JsonResponse
    {
        $order = MarketOrder::with(['lines.item', 'shop.owner', 'buyer', 'review'])->find($id);
        if (!$order || !$order->roleOf($request->user()->id)) {
            return $this->fail('order', translate('This order is not available.'));
        }
        return $order;
    }

    private function ok(Request $request, MarketOrder $order): JsonResponse
    {
        $order->loadMissing(['lines.item', 'shop', 'buyer', 'review']);
        return response()->json(responseFormatter(DEFAULT_200, MarketPresenter::order($order, $request->user()->id, true)));
    }

    private function fail(string $code, string $message): JsonResponse
    {
        return response()->json(responseFormatter(DEFAULT_400, errors: [['error_code' => $code, 'message' => $message]]), 403);
    }
}
