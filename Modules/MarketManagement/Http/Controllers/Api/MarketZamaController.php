<?php

namespace Modules\MarketManagement\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\MarketManagement\Entities\MarketConversation;
use Modules\MarketManagement\Entities\MarketItem;
use Modules\MarketManagement\Entities\MarketMessage;
use Modules\MarketManagement\Entities\MarketZamaDeal;
use Modules\MarketManagement\Lib\MarketSettings;
use Modules\MarketManagement\Service\ZamaService;
use Modules\MarketManagement\Transformers\MarketPresenter;

/**
 * Zama (reselling) for the customer app: the catalogue of items open to zama, adding one
 * to your shop, quotes and offers in a buyer's chat, and the deal with the owner.
 */
class MarketZamaController extends Controller
{
    public function __construct(protected ZamaService $zama)
    {
    }

    /** Items open to zama, for resellers. */
    public function catalogue(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => 'nullable|string|max:80',
            'category' => ['nullable', Rule::in(MarketSettings::CATEGORIES)],
            'offset' => 'nullable|integer|min:0',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);
        $viewer = $request->user()->id;
        $offset = (int)($data['offset'] ?? 0);
        $result = $this->zama->catalogue($request->user(), $data, $offset, (int)($data['limit'] ?? 20));
        return response()->json(responseFormatter(DEFAULT_200, [
            'enabled' => MarketSettings::all()['zama_enabled'],
            'total' => $result['total'],
            'offset' => $offset,
            'items' => $result['items']->map(fn(MarketItem $i) => MarketPresenter::zamaSource($i, $viewer))->all(),
            'counts' => $this->zama->counts($request->user()),
        ]));
    }

    /** One item open to zama, in full, for a reseller deciding. */
    public function item(Request $request, string $code): JsonResponse
    {
        $item = MarketItem::with(['photos', 'shop'])->where('code', $code)->whereNull('zama_of')->first();
        if (!$item || !$item->zamaAvailable()) {
            return $this->fail('item', translate('This item is not open to zama any more.'), 404);
        }
        return response()->json(responseFormatter(DEFAULT_200, MarketPresenter::zamaSource($item, $request->user()->id, true)));
    }

    /** "Sell this (zama)": into the reseller's shop, with their price or none ("Ask for price"). */
    public function take(Request $request, string $code): JsonResponse
    {
        $data = $request->validate(['price' => 'nullable|numeric|min:1|max:100000000']);
        $item = MarketItem::with(['photos', 'shop'])->where('code', $code)->whereNull('zama_of')->first();
        if (!$item) {
            return $this->fail('item', translate('This item is not open to zama any more.'), 404);
        }
        $copy = $this->zama->take($request->user(), $item, isset($data['price']) ? (float)$data['price'] : null);
        return response()->json(responseFormatter(DEFAULT_STORE_200, MarketPresenter::item($copy, $request->user()->id, true)));
    }

    public function price(Request $request, string $code): JsonResponse
    {
        $data = $request->validate(['price' => 'nullable|numeric|min:1|max:100000000']);
        $copy = MarketItem::with(['photos', 'shop'])->where('code', $code)->whereNotNull('zama_of')->first();
        if (!$copy) {
            return $this->fail('item', translate('This item is not yours.'), 404);
        }
        $copy = $this->zama->setPrice($request->user(), $copy, isset($data['price']) ? (float)$data['price'] : null);
        return response()->json(responseFormatter(DEFAULT_200, MarketPresenter::item($copy, $request->user()->id, true)));
    }

    // ------------------------------------------------------------ quotes

    public function quote(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'price' => 'required|numeric|min:1|max:100000000',
            'quantity' => 'nullable|integer|min:1|max:1000',
            'colour' => 'nullable|string|max:40',
            'size' => 'nullable|string|max:20',
            'hours' => 'nullable|integer|min:1|max:168',
        ]);
        $chat = $this->chat($request, $id);
        if ($chat instanceof JsonResponse) {
            return $chat;
        }
        $message = $this->zama->sendQuote($request->user(), $chat, (float)$data['price'], (int)($data['quantity'] ?? 1),
            $data['colour'] ?? null, $data['size'] ?? null, isset($data['hours']) ? (int)$data['hours'] : null);
        return response()->json(responseFormatter(DEFAULT_STORE_200, MarketPresenter::message($message, $request->user()->id)));
    }

    public function offer(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'price' => 'required|numeric|min:1|max:100000000',
            'quantity' => 'nullable|integer|min:1|max:1000',
            'body' => 'nullable|string|max:300',
        ]);
        $chat = $this->chat($request, $id);
        if ($chat instanceof JsonResponse) {
            return $chat;
        }
        $message = $this->zama->sendOffer($request->user(), $chat, (float)$data['price'], (int)($data['quantity'] ?? 1), $data['body'] ?? null);
        return response()->json(responseFormatter(DEFAULT_STORE_200, MarketPresenter::message($message, $request->user()->id)));
    }

    public function answer(Request $request, string $messageId): JsonResponse
    {
        $data = $request->validate(['accept' => 'required|boolean']);
        $quote = MarketMessage::with('conversation')->find($messageId);
        if (!$quote) {
            return $this->fail('quote', translate('This price is no longer open.'), 404);
        }
        $quote = $this->zama->answerQuote($request->user(), $quote, (bool)$data['accept']);
        return response()->json(responseFormatter(DEFAULT_200, MarketPresenter::message($quote, $request->user()->id)));
    }

    /** The reseller asks the owner to keep it, once the buyer accepted. */
    public function startDeal(Request $request, string $messageId): JsonResponse
    {
        $data = $request->validate(['way' => ['required', Rule::in(MarketZamaDeal::WAYS)]]);
        $quote = MarketMessage::with('conversation')->find($messageId);
        if (!$quote) {
            return $this->fail('quote', translate('This price is no longer open.'), 404);
        }
        $deal = $this->zama->startDeal($request->user(), $quote, $data['way']);
        return $this->deal($request, $deal);
    }

    // ------------------------------------------------------------ deals

    public function deals(Request $request): JsonResponse
    {
        $as = in_array($request->query('as'), ['owner', 'buyer', 'reseller'], true) ? $request->query('as') : 'reseller';
        $viewer = $request->user()->id;
        return response()->json(responseFormatter(DEFAULT_200, [
            'deals' => $this->zama->deals($request->user(), $as)->map(fn(MarketZamaDeal $d) => MarketPresenter::zamaDeal($d, $viewer))->filter()->values()->all(),
            'counts' => $this->zama->counts($request->user()),
            'record' => $as === 'owner' && ($shop = app(\Modules\MarketManagement\Service\ShopService::class)->mine($request->user()))
                ? $this->zama->ownerRecord($shop->id) : null,
        ]));
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $deal = $this->find($request, $id);
        return $deal instanceof JsonResponse ? $deal : $this->deal($request, $deal);
    }

    public function act(Request $request, string $id, string $action): JsonResponse
    {
        $deal = $this->find($request, $id);
        if ($deal instanceof JsonResponse) {
            return $deal;
        }
        $user = $request->user();
        $deal = match ($action) {
            'keep' => $this->zama->keep($user, $deal, $request->validate(['hours' => 'nullable|integer|min:1|max:72'])['hours'] ?? null),
            'decline' => $this->zama->decline($user, $deal, $request->validate(['reason' => ['nullable', Rule::in(MarketZamaDeal::DECLINE_REASONS)]])['reason'] ?? 'other'),
            'handed-over' => $this->zama->handedOver($user, $deal),
            'sold' => $this->zama->sold($user, $deal, (string)$request->validate(['code' => 'required|string|max:10'])['code']),
            'cancel' => $this->zama->cancel($user, $deal),
            'bought' => $this->zama->buyerBought($user, $deal),
            'margin-paid' => $this->zama->marginPaid($user, $deal),
            'margin-received' => $this->zama->marginReceived($user, $deal),
        };
        return $this->deal($request, $deal);
    }

    // ------------------------------------------------------------ owner

    public function resellers(Request $request): JsonResponse
    {
        return response()->json(responseFormatter(DEFAULT_200, ['resellers' => $this->zama->resellers($request->user())->all()]));
    }

    public function removeReseller(Request $request, string $userId): JsonResponse
    {
        return response()->json(responseFormatter(DEFAULT_200, ['removed' => $this->zama->removeReseller($request->user(), $userId)]));
    }

    // ------------------------------------------------------------ inside

    private function deal(Request $request, MarketZamaDeal $deal): JsonResponse
    {
        $deal = MarketZamaDeal::with(['source.photos', 'ownerShop', 'reseller', 'buyer', 'copy'])->find($deal->id);
        return response()->json(responseFormatter(DEFAULT_200, MarketPresenter::zamaDeal($deal, $request->user()->id)));
    }

    private function find(Request $request, string $id): MarketZamaDeal|JsonResponse
    {
        $deal = MarketZamaDeal::find($id);
        if (!$deal || !$deal->roleOf($request->user()->id)) {
            return $this->fail('deal', translate('This deal is not yours.'), 404);
        }
        return $deal;
    }

    private function chat(Request $request, string $id): MarketConversation|JsonResponse
    {
        $chat = MarketConversation::with(['item.zamaSource.shop', 'shop', 'buyer', 'seller'])->find($id);
        if (!$chat || !$chat->hasParty($request->user()->id)) {
            return $this->fail('chat', translate('This chat is not yours.'), 404);
        }
        return $chat;
    }

    private function fail(string $code, string $message, int $status = 403): JsonResponse
    {
        return response()->json(responseFormatter(DEFAULT_400, errors: [['error_code' => $code, 'message' => $message]]), $status);
    }
}
