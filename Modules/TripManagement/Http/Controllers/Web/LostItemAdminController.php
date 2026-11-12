<?php

namespace Modules\TripManagement\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\TripManagement\Entities\LostItem;
use Modules\TripManagement\Lib\LostItemSettings;
use Modules\TripManagement\Service\LostItemService;

/** Trip management > Lost and found: every case, the ones support must handle first, and the settings. */
class LostItemAdminController extends Controller
{
    public const TABS = ['support' => 'Needs support', 'open' => 'Open', 'returned' => 'Returned', 'closed' => 'Closed', 'all' => 'All'];

    public function index(Request $request): View
    {
        $this->authorize('trip_view');
        $tab = array_key_exists($request->query('tab'), self::TABS) ? $request->query('tab') : 'support';
        $items = LostItem::with([
            'trip:id,ref_id,created_at', 'trip.coordinate:id,trip_request_id,pickup_address,destination_address',
            'customer:id,first_name,last_name,phone', 'driver:id,first_name,last_name,phone',
        ])
            ->when($tab === 'support', fn($q) => $q->whereIn('status', LostItem::OPEN)->whereNotNull('escalated_at'))
            ->when($tab === 'open', fn($q) => $q->whereIn('status', LostItem::OPEN))
            ->when($tab === 'returned', fn($q) => $q->where('status', LostItem::RETURNED))
            ->when($tab === 'closed', fn($q) => $q->whereIn('status', [LostItem::CANCELLED, LostItem::CLOSED]))
            ->when($request->filled('q'), fn($q) => $q->where(fn($w) => $w->where('description', 'like', '%' . $request->query('q') . '%')
                ->orWhereHas('trip', fn($t) => $t->where('ref_id', $request->query('q')))))
            ->orderByRaw('escalated_at IS NULL')->latest()
            ->paginate(paginationLimit())->withQueryString();
        $counts = [
            'support' => LostItem::whereIn('status', LostItem::OPEN)->whereNotNull('escalated_at')->count(),
            'open' => LostItem::whereIn('status', LostItem::OPEN)->count(),
        ];
        $settings = LostItemSettings::all();
        return view('tripmanagement::admin.lost-item.index', compact('items', 'tab', 'counts', 'settings'));
    }

    /** Staff step in: confirm found / not found on the driver's behalf, mark it back, close it, or keep a note. */
    public function act(Request $request, LostItem $item, LostItemService $service): RedirectResponse
    {
        $this->authorize('trip_edit');
        $data = $request->validate([
            'action' => ['required', Rule::in(['note', 'found', 'not_found', 'returned', 'close'])],
            'admin_note' => 'nullable|string|max:2000',
        ]);
        $note = trim((string)($data['admin_note'] ?? '')) ?: null;
        if ($data['action'] !== 'note' && !$item->isOpen()) {
            Toastr::error(translate('This case is already closed.'));
            return back();
        }
        if ($note !== null) {
            $item->update(['admin_note' => $note]);
        }
        match ($data['action']) {
            'found' => $service->answer($item, true, null, 'admin'),
            'not_found' => $service->answer($item, false, null, 'admin'),
            'returned' => $service->confirmReturned($item, 'admin'),
            'close' => $service->adminClose($item, $note),
            default => null,
        };
        Toastr::success(translate('Lost item case updated'));
        return back();
    }

    public function settings(Request $request): RedirectResponse
    {
        $this->authorize('trip_edit');
        $data = $request->validate([
            'lost_items_enabled' => 'nullable|boolean',
            'lost_items_report_days' => 'required|integer|min:1|max:30',
            'lost_items_answer_hours' => 'required|integer|min:1|max:72',
            'lost_items_return_fee' => 'required|numeric|min:0|max:100000',
            'lost_items_stale_days' => 'required|integer|min:1|max:14',
            'lost_items_confirm_hours' => 'required|integer|min:1|max:168',
        ]);
        $data['lost_items_enabled'] = $request->boolean('lost_items_enabled') ? 1 : 0;
        foreach ($data as $key => $value) {
            updateBusinessSetting(keyName: $key, settingType: LostItemSettings::TYPE, value: (string)$value);
        }
        Cache::forget(CACHE_BUSINESS_SETTINGS);
        Toastr::success(translate('Lost and found settings saved'));
        return back();
    }
}
