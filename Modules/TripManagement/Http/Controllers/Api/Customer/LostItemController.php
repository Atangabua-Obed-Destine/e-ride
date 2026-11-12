<?php

namespace Modules\TripManagement\Http\Controllers\Api\Customer;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Modules\TripManagement\Entities\LostItem;
use Modules\TripManagement\Entities\TripRequest;
use Modules\TripManagement\Service\LostItemService;

/** The rider's side of lost and found: report, choose how it comes back, confirm, ask support, or close. */
class LostItemController extends Controller
{
    public function __construct(private readonly LostItemService $items)
    {
    }

    /** The rider's cases, newest first; ?trip_request_id= gives the one for a trip. */
    public function index(Request $request): JsonResponse
    {
        $list = LostItem::where('customer_id', auth('api')->id())
            ->when($request->filled('trip_request_id'), fn($q) => $q->where('trip_request_id', $request->query('trip_request_id')))
            ->latest()->limit(50)->get()
            ->map(fn(LostItem $item) => $this->items->present($item, 'customer'));
        return response()->json(responseFormatter(DEFAULT_200, $list));
    }

    public function show(string $id): JsonResponse
    {
        $item = $this->mine($id);
        return $item ? response()->json(responseFormatter(DEFAULT_200, $this->items->present($item, 'customer')))
            : $this->problem(translate('This lost item report was not found.'), 404);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'trip_request_id' => 'required|string',
            'description' => 'required|string|min:3|max:1000',
            'photo' => 'nullable|image|max:5120',
        ]);
        $trip = $this->items->reportableTrip($data['trip_request_id'], auth('api')->id());
        if (!$trip instanceof TripRequest) {
            return $this->problem($trip);
        }
        $item = $this->items->report($trip, trim($data['description']), $request->file('photo'));
        return response()->json(responseFormatter(DEFAULT_STORE_200, $this->items->present($item->fresh(), 'customer')));
    }

    /** collect | deliver, | returned (it is back), | support, | cancel (found it myself). */
    public function act(Request $request, string $id): JsonResponse
    {
        $data = $request->validate(['action' => ['required', Rule::in(['collect', 'deliver', 'returned', 'support', 'cancel'])]]);
        $item = $this->mine($id);
        if (!$item) {
            return $this->problem(translate('This lost item report was not found.'), 404);
        }
        if (!in_array($data['action'], $this->items->actions($item, 'customer'), true)) {
            return $this->problem(translate('This case has moved on. Pull down to refresh it.'));
        }
        match ($data['action']) {
            'collect' => $this->items->chooseReturn($item, LostItem::COLLECT),
            'deliver' => $this->items->chooseReturn($item, LostItem::DELIVER),
            'returned' => $this->items->confirmReturned($item, 'customer'),
            'support' => $this->items->escalate($item, 'asked'),
            'cancel' => $this->items->cancel($item),
        };
        return response()->json(responseFormatter(DEFAULT_UPDATE_200, $this->items->present($item->fresh(), 'customer')));
    }

    protected function mine(string $id): ?LostItem
    {
        return LostItem::whereKey($id)->where('customer_id', auth('api')->id())->first();
    }

    protected function problem(string $message, int $code = 403): JsonResponse
    {
        return response()->json(responseFormatter(DEFAULT_400, errors: [['error_code' => 'lost_item', 'message' => $message]]), $code);
    }
}
