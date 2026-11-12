<?php

namespace Modules\TripManagement\Http\Controllers\Api\Driver;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\TripManagement\Entities\LostItem;
use Modules\TripManagement\Service\LostItemService;

/** The driver's side of lost and found: answer Found it / Not in my car, then say it is back. */
class LostItemController extends Controller
{
    public function __construct(private readonly LostItemService $items)
    {
    }

    /** Open cases first (those waiting for an answer at the top), then the last closed ones. */
    public function index(): JsonResponse
    {
        $list = LostItem::where('driver_id', auth('api')->id())
            ->orderByRaw("FIELD(status, 'reported', 'found', 'not_found') = 0, FIELD(status, 'reported', 'found', 'not_found')")
            ->latest()->limit(50)->get()
            ->map(fn(LostItem $item) => $this->items->present($item, 'driver'));
        return response()->json(responseFormatter(DEFAULT_200, [
            'waiting' => LostItem::where('driver_id', auth('api')->id())->where('status', LostItem::REPORTED)->count(),
            'items' => $list,
        ]));
    }

    public function show(string $id): JsonResponse
    {
        $item = $this->mine($id);
        return $item ? response()->json(responseFormatter(DEFAULT_200, $this->items->present($item, 'driver')))
            : $this->problem(translate('This lost item report was not found.'), 404);
    }

    /** found | not_found (with an optional note: where it is), | returned. */
    public function act(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['found', 'not_found', 'returned'])],
            'note' => 'nullable|string|max:500',
        ]);
        $item = $this->mine($id);
        if (!$item) {
            return $this->problem(translate('This lost item report was not found.'), 404);
        }
        if (!in_array($data['action'], $this->items->actions($item, 'driver'), true)) {
            return $this->problem(translate('This case has moved on. Pull down to refresh it.'));
        }
        match ($data['action']) {
            'found' => $this->items->answer($item, true, $data['note'] ?? null),
            'not_found' => $this->items->answer($item, false, $data['note'] ?? null),
            'returned' => $this->items->driverReturned($item),
        };
        return response()->json(responseFormatter(DEFAULT_UPDATE_200, $this->items->present($item->fresh(), 'driver')));
    }

    protected function mine(string $id): ?LostItem
    {
        return LostItem::whereKey($id)->where('driver_id', auth('api')->id())->first();
    }

    protected function problem(string $message, int $code = 403): JsonResponse
    {
        return response()->json(responseFormatter(DEFAULT_400, errors: [['error_code' => 'lost_item', 'message' => $message]]), $code);
    }
}
