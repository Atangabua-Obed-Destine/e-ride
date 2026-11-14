<?php

namespace Modules\MarketManagement\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\MarketManagement\Entities\MarketConversation;
use Modules\MarketManagement\Entities\MarketItem;
use Modules\MarketManagement\Entities\MarketMessage;
use Modules\MarketManagement\Service\MarketChatService;
use Modules\MarketManagement\Transformers\MarketPresenter;

/**
 * Customer app, E-Ride Market chat. The app polls a chat while it is open (after=<last id>)
 * and gets a push otherwise.
 */
class MarketChatController extends Controller
{
    public function __construct(protected MarketChatService $chats)
    {
    }

    public function inbox(Request $request): JsonResponse
    {
        $viewer = $request->user()->id;
        return response()->json(responseFormatter(DEFAULT_200, [
            'unread' => $this->chats->unread($request->user()),
            'chats' => $this->chats->inbox($request->user())->map(fn(MarketConversation $c) => MarketPresenter::conversation($c, $viewer))->all(),
        ]));
    }

    /** The buyer taps "Chat with seller" on an item. */
    public function start(Request $request, string $code): JsonResponse
    {
        $item = MarketItem::with(['shop', 'photos'])->where('code', $code)->first();
        if (!$item) {
            return $this->fail('item', translate('This item is no longer available.'));
        }
        $conversation = $this->chats->start($request->user(), $item);
        return response()->json(responseFormatter(DEFAULT_200, MarketPresenter::conversation($conversation->load(['item.photos', 'shop', 'buyer']), $request->user()->id)));
    }

    public function messages(Request $request, string $id): JsonResponse
    {
        $request->validate(['after' => 'nullable|string|max:40']);
        $conversation = $this->own($request, $id);
        if ($conversation instanceof JsonResponse) {
            return $conversation;
        }
        $viewer = $request->user()->id;
        $messages = $this->chats->messages($request->user(), $conversation, $request->query('after'));
        return response()->json(responseFormatter(DEFAULT_200, [
            'chat' => MarketPresenter::conversation($conversation->fresh(['item.photos', 'shop', 'buyer']), $viewer),
            'messages' => $messages->map(fn(MarketMessage $m) => MarketPresenter::message($m, $viewer))->all(),
        ]));
    }

    public function send(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(MarketMessage::TYPES)],
            'body' => 'nullable|string|max:2000',
            'photo' => 'nullable|image|max:' . convertBytesToKiloBytes(maxUploadSize('image')),
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'address' => 'nullable|string|max:191',
            'trip_id' => 'nullable|string|max:40',
        ]);
        $conversation = $this->own($request, $id);
        if ($conversation instanceof JsonResponse) {
            return $conversation;
        }
        $message = $this->chats->send($request->user(), $conversation, $data['type'], $data['body'] ?? null, $request->file('photo'),
            array_filter(['latitude' => $data['latitude'] ?? null, 'longitude' => $data['longitude'] ?? null,
                'address' => $data['address'] ?? null, 'trip_id' => $data['trip_id'] ?? null], fn($v) => $v !== null));
        return response()->json(responseFormatter(DEFAULT_STORE_200, MarketPresenter::message($message, $request->user()->id)));
    }

    /** The seller stops a buyer (or allows them again) in this chat. */
    public function blockBuyer(Request $request, string $id): JsonResponse
    {
        $conversation = $this->own($request, $id);
        if ($conversation instanceof JsonResponse) {
            return $conversation;
        }
        $conversation = $this->chats->blockBuyer($request->user(), $conversation, $request->boolean('on', true));
        return response()->json(responseFormatter(DEFAULT_200, MarketPresenter::conversation($conversation->load(['item.photos', 'shop', 'buyer']), $request->user()->id)));
    }

    private function own(Request $request, string $id): MarketConversation|JsonResponse
    {
        $conversation = MarketConversation::find($id);
        if (!$conversation || !$conversation->hasParty($request->user()->id)) {
            return $this->fail('chat', translate('This chat is not yours.'));
        }
        return $conversation;
    }

    private function fail(string $code, string $message): JsonResponse
    {
        return response()->json(responseFormatter(DEFAULT_400, errors: [['error_code' => $code, 'message' => $message]]), 403);
    }
}
