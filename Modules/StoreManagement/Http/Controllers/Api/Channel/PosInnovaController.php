<?php

namespace Modules\StoreManagement\Http\Controllers\Api\Channel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use Modules\StoreManagement\Entities\StorePosLink;
use Modules\StoreManagement\Service\PosChannelService;

/**
 * /api/channel/posinnova/v1: what POS Innova calls (docs/posinnova-channel-contract.md).
 * Every request carries "Authorization: Bearer erk_..." for one linked branch.
 */
class PosInnovaController extends Controller
{
    public function __construct(protected PosChannelService $channel)
    {
    }

    protected function link(Request $request): StorePosLink|JsonResponse
    {
        $link = $this->channel->authenticate($request->bearerToken());
        if (!$link) {
            return self::error(401, 'invalid_key', 'This key is not known to E-Ride.');
        }
        if ($link->revoked_at) {
            return self::error(401, 'link_revoked', 'This E-Ride link was removed. Link the POS again from the E-Ride partner portal.');
        }
        if ($v = $request->header('X-POS-Version')) {
            $link->forceFill(['pos_version' => mb_substr($v, 0, 40)])->saveQuietly();
        }
        return $link;
    }

    public static function error(int $status, string $code, string $message): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
    }

    /** Runs $fn with the linked branch, turning validation errors into the contract's error shape. */
    protected function with(Request $request, callable $fn): JsonResponse
    {
        $link = $this->link($request);
        if ($link instanceof JsonResponse) {
            return $link;
        }
        try {
            $out = $fn($link);
        } catch (ValidationException $e) {
            return self::error(422, 'invalid', collect($e->errors())->flatten()->first() ?? 'Invalid request.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return self::error($e->getStatusCode(), $e->getStatusCode() === 404 ? 'order_not_found' : 'error', $e->getStatusCode() === 404 ? 'No such order for this branch.' : 'Request refused.');
        }
        if (is_array($out) && isset($out['conflict'])) {
            return response()->json(['error' => ['code' => $out['conflict'], 'message' => str_replace('_', ' ', $out['conflict'])]]
                + (isset($out['pos_sale_id']) ? ['pos_sale_id' => $out['pos_sale_id']] : []), 409);
        }
        return response()->json($out);
    }

    public function hello(Request $request): JsonResponse
    {
        return $this->with($request, fn($link) => $this->channel->hello($link));
    }

    public function heartbeat(Request $request): JsonResponse
    {
        return $this->with($request, fn($link) => $this->channel->heartbeat($link, $request->all()));
    }

    public function catalogFull(Request $request): JsonResponse
    {
        return $this->with($request, fn($link) => $this->channel->catalogFull($link, $request->all()));
    }

    public function catalogChanges(Request $request): JsonResponse
    {
        return $this->with($request, fn($link) => $this->channel->catalogChanges($link, $request->all()));
    }

    public function image(Request $request): JsonResponse
    {
        return $this->with($request, function ($link) use ($request) {
            $request->validate(['sha1' => 'required|string|size:40', 'file' => 'required|file|mimes:jpg,jpeg,png,webp|max:5120']);
            $this->channel->storeImage($link, strtolower($request->input('sha1')), $request->file('file'));
            return ['ok' => true];
        });
    }

    public function pending(Request $request): JsonResponse
    {
        return $this->with($request, fn($link) => $this->channel->pending($link));
    }

    public function order(Request $request, string $id): JsonResponse
    {
        return $this->with($request, fn($link) => $this->channel->orderPayload($link, $this->channel->find($link, $id)));
    }

    public function rungUp(Request $request, string $id): JsonResponse
    {
        return $this->with($request, fn($link) => $this->channel->rungUp($link, $this->channel->find($link, $id), $request->all()));
    }

    public function unrung(Request $request, string $id): JsonResponse
    {
        return $this->with($request, fn($link) => $this->channel->unrung($link, $this->channel->find($link, $id), $request->all()));
    }

    public function cancelSeen(Request $request, string $id): JsonResponse
    {
        return $this->with($request, fn($link) => $this->channel->cancelSeen($link, $this->channel->find($link, $id)));
    }
}
