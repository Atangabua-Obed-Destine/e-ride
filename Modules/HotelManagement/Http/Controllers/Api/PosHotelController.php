<?php

namespace Modules\HotelManagement\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Modules\HotelManagement\Entities\HotelPosLink;
use Modules\HotelManagement\Service\HotelPosService;

/**
 * /api/channel/posinnova/v1/hotel: what POS Innova's hotel module calls (docs/posinnova-hotel-contract.md).
 * Every request carries "Authorization: Bearer erh_..." for one linked hotel. Every POST may carry an
 * Idempotency-Key: the same key within 48 hours gets the first answer again, without doing anything.
 */
class PosHotelController extends Controller
{
    public function __construct(protected HotelPosService $pos)
    {
    }

    public static function error(int $status, string $code, string $message): JsonResponse
    {
        return response()->json(['error' => ['code' => $code, 'message' => $message]], $status);
    }

    protected function with(Request $request, callable $fn): JsonResponse
    {
        $link = $this->pos->authenticate($request->bearerToken());
        if (!$link) {
            return self::error(401, 'invalid_key', 'This key is not known to E-Ride.');
        }
        if ($link->revoked_at) {
            return self::error(401, 'link_revoked', 'This E-Ride link was removed. Link POS again from the E-Ride hotel portal.');
        }
        if ($v = $request->header('X-POS-Version')) {
            $link->forceFill(['pos_version' => mb_substr($v, 0, 40)])->saveQuietly();
        }
        $idem = $request->isMethod('POST') ? trim((string)$request->header('Idempotency-Key')) : '';
        $cacheKey = $idem !== '' ? 'hpos-idem:' . $link->id . ':' . sha1($idem) : null;
        if ($cacheKey && ($seen = Cache::get($cacheKey))) {
            return response()->json($seen['body'], $seen['status'])->header('Idempotent-Replay', 'true');
        }
        try {
            $out = $fn($link);
        } catch (ValidationException $e) {
            return self::error(422, 'invalid', collect($e->errors())->flatten()->first() ?? 'Invalid request.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            return $e->getStatusCode() === 404
                ? self::error(404, 'inquiry_not_found', 'No such inquiry for this hotel.')
                : self::error($e->getStatusCode(), 'error', 'Request refused.');
        }
        if (is_array($out) && isset($out['conflict'])) {
            $body = ['error' => ['code' => $out['conflict'], 'message' => str_replace('_', ' ', $out['conflict'])]];
            $status = 409;
        } else {
            $body = $out;
            $status = 200;
        }
        if ($cacheKey) {
            Cache::put($cacheKey, ['status' => $status, 'body' => $body], now()->addHours(48));
        }
        return response()->json($body, $status);
    }

    public function hello(Request $r): JsonResponse
    {
        return $this->with($r, fn(HotelPosLink $l) => $this->pos->hello($l));
    }

    public function heartbeat(Request $r): JsonResponse
    {
        return $this->with($r, fn(HotelPosLink $l) => $this->pos->heartbeat($l));
    }

    public function availabilityFull(Request $r): JsonResponse
    {
        return $this->with($r, fn(HotelPosLink $l) => $this->pos->availabilityFull($l, $r->all()));
    }

    public function availabilityChanges(Request $r): JsonResponse
    {
        return $this->with($r, fn(HotelPosLink $l) => $this->pos->availabilityChanges($l, $r->all()));
    }

    public function pending(Request $r): JsonResponse
    {
        return $this->with($r, fn(HotelPosLink $l) => $this->pos->pending($l));
    }

    public function inquiry(Request $r, string $id): JsonResponse
    {
        return $this->with($r, fn(HotelPosLink $l) => $this->pos->payload($l, $this->pos->find($l, $id)));
    }

    public function kept(Request $r, string $id): JsonResponse
    {
        return $this->with($r, fn(HotelPosLink $l) => $this->pos->kept($l, $this->pos->find($l, $id), $r->all()));
    }

    public function declined(Request $r, string $id): JsonResponse
    {
        return $this->with($r, fn(HotelPosLink $l) => $this->pos->declined($l, $this->pos->find($l, $id), $r->all()));
    }

    public function reply(Request $r, string $id): JsonResponse
    {
        return $this->with($r, fn(HotelPosLink $l) => $this->pos->reply($l, $this->pos->find($l, $id), $r->all()));
    }

    public function event(Request $r, string $id): JsonResponse
    {
        return $this->with($r, fn(HotelPosLink $l) => $this->pos->event($l, $this->pos->find($l, $id), $r->all()));
    }

    public function releaseSeen(Request $r, string $id): JsonResponse
    {
        return $this->with($r, fn(HotelPosLink $l) => $this->pos->releaseSeen($l, $this->pos->find($l, $id), $r->all()));
    }
}
