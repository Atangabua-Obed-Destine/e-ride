<?php

namespace Modules\MarketManagement\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\MarketManagement\Entities\MarketAuction;
use Modules\MarketManagement\Entities\MarketItem;
use Modules\MarketManagement\Entities\MarketOrder;
use Modules\MarketManagement\Lib\MarketSettings;
use Modules\MarketManagement\Service\AuctionService;
use Modules\MarketManagement\Service\MarketOrderService;
use Modules\MarketManagement\Service\ShopService;
use Modules\MarketManagement\Transformers\MarketPresenter;

/** Customer app, E-Ride Market auctions: bidding, the bids, and what the seller does after the end. */
class MarketAuctionController extends Controller
{
    public function __construct(protected AuctionService $auctions, protected MarketOrderService $orders, protected ShopService $shops)
    {
    }

    public function bid(Request $request, string $code): JsonResponse
    {
        if (!MarketSettings::enabled()) {
            return $this->fail('market', translate('E-Ride Market is not available right now.'));
        }
        $request->validate(['amount' => 'required|numeric|min:1|max:1000000000']);
        $auction = $this->auctionOf($code);
        if ($auction instanceof JsonResponse) {
            return $auction;
        }
        $this->auctions->bid($request->user(), $auction, (float)$request->input('amount'));
        return $this->item($request, $code);
    }

    public function bids(Request $request, string $code): JsonResponse
    {
        $auction = $this->auctionOf($code);
        if ($auction instanceof JsonResponse) {
            return $auction;
        }
        return response()->json(responseFormatter(DEFAULT_200, ['bids' => MarketPresenter::bids($auction, $request->user()->id)]));
    }

    public function offerNext(Request $request, string $code): JsonResponse
    {
        $auction = $this->auctionOf($code);
        if ($auction instanceof JsonResponse) {
            return $auction;
        }
        $this->auctions->offerNext($request->user(), $auction);
        return $this->item($request, $code);
    }

    public function answerOffer(Request $request, string $code): JsonResponse
    {
        $request->validate(['accept' => 'required|boolean']);
        $auction = $this->auctionOf($code);
        if ($auction instanceof JsonResponse) {
            return $auction;
        }
        $order = $this->auctions->answerOffer($request->user(), $auction, $request->boolean('accept'));
        return response()->json(responseFormatter(DEFAULT_200, ['order_id' => $order?->id]));
    }

    public function relist(Request $request, string $code): JsonResponse
    {
        $request->validate(['auction_days' => 'nullable|integer|min:1|max:30', 'auction_hours' => 'nullable|integer|min:1|max:720']);
        $auction = $this->auctionOf($code);
        if ($auction instanceof JsonResponse) {
            return $auction;
        }
        $this->auctions->relist($request->user(), $auction, $request->only(['auction_days', 'auction_hours']));
        return $this->item($request, $code);
    }

    public function paymentArranged(Request $request, string $id): JsonResponse
    {
        $order = MarketOrder::find($id);
        if (!$order || $order->seller_id !== $request->user()->id) {
            return $this->fail('order', translate('This order is not available.'));
        }
        $order = $this->orders->paymentArranged($request->user(), $order);
        return response()->json(responseFormatter(DEFAULT_200, MarketPresenter::order($order->loadMissing(['lines.item', 'shop', 'buyer', 'review']), $request->user()->id, true)));
    }

    /** The winner did not arrange payment in time: a strike, the order closes, and the seller can offer it to the next bidder. */
    public function noShow(Request $request, string $id): JsonResponse
    {
        $order = MarketOrder::find($id);
        if (!$order || $order->seller_id !== $request->user()->id) {
            return $this->fail('order', translate('This order is not available.'));
        }
        $auction = $this->auctions->noShow($request->user(), $order);
        return response()->json(responseFormatter(DEFAULT_200, ['item_code' => $auction->item?->code]));
    }

    private function auctionOf(string $code): MarketAuction|JsonResponse
    {
        $item = MarketItem::where('code', $code)->first();
        $auction = $item?->auction;
        if (!$item || !$item->isAuction() || !$auction) {
            return $this->fail('auction', translate('This auction is not available.'));
        }
        return $auction;
    }

    private function item(Request $request, string $code): JsonResponse
    {
        $item = MarketItem::with(['photos', 'shop', 'auction'])->where('code', $code)->first();
        $viewer = $request->user()->id;
        // Same shape as GET items/{code}, so the app keeps "Following" after a bid.
        return response()->json(responseFormatter(DEFAULT_200, MarketPresenter::item($item, $viewer, true) + [
            'following' => $this->shops->follows($viewer, $item->shop_id),
            'follow_notify' => $this->shops->followNotify($viewer, $item->shop_id),
        ]));
    }

    private function fail(string $code, string $message): JsonResponse
    {
        return response()->json(responseFormatter(DEFAULT_400, errors: [['error_code' => $code, 'message' => $message]]), 403);
    }
}
