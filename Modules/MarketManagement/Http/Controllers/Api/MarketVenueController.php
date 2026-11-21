<?php

namespace Modules\MarketManagement\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\MarketManagement\Entities\MarketShop;
use Modules\MarketManagement\Entities\MarketVenue;
use Modules\MarketManagement\Service\VenueService;
use Modules\MarketManagement\Transformers\MarketPresenter;

/** Markets (Bamenda Main Market...) and the sheds inside them, for buyers and for shop owners choosing theirs. */
class MarketVenueController extends Controller
{
    public function __construct(protected VenueService $venues)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $lat = is_numeric($request->query('lat')) ? (float)$request->query('lat') : null;
        $lng = is_numeric($request->query('lng')) ? (float)$request->query('lng') : null;
        return response()->json(responseFormatter(DEFAULT_200, [
            'venues' => $this->venues->list($lat, $lng)->map(fn(MarketVenue $v) => MarketPresenter::venue($v))->values()->all(),
        ]));
    }

    /** One market and its shops; q searches what they sell, verified=1 and block narrow it. */
    public function show(Request $request, string $slug): JsonResponse
    {
        $venue = MarketVenue::where('slug', $slug)->where('is_active', true)
            ->withCount(['liveShops as shops_count', 'liveShops as verified_count' => fn($q) => $q->whereNotNull('verified_at')])
            ->firstOrFail();
        $block = trim((string)$request->query('block', '')) ?: null;
        $result = $this->venues->shops($venue, mb_substr(trim((string)$request->query('q', '')), 0, 60), $request->boolean('verified'), $block);
        $viewer = $request->user()?->id;
        return response()->json(responseFormatter(DEFAULT_200, [
            'venue' => MarketPresenter::venue($venue),
            'searched' => $result['searched'],
            'shops' => $result['shops']->map(fn(MarketShop $s) => MarketPresenter::shop($s, $viewer) + [
                'matched_items' => $result['matches'][$s->id] ?? [],
            ])->values()->all(),
        ]));
    }
}
