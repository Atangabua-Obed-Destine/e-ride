<?php

namespace Modules\PlaceManagement\Http\Controllers\Web\Admin;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\PlaceManagement\Entities\Place;
use Modules\PlaceManagement\Service\Interfaces\PlaceServiceInterface;

/**
 * Admin: this week's on-duty pharmacies (pharmacie de garde), from the
 * official rota. Entries carry their dates and drop out of the app on their
 * own when the dates pass, so a list nobody updated never shows.
 */
class OnDutyController extends Controller
{
    public function __construct(protected PlaceServiceInterface $places)
    {
    }

    public function index(): View
    {
        $pharmacies = Place::where('explore_category', 'pharmacy')->where('is_active', 1)->orderBy('name')->get();
        $current = $pharmacies->filter(fn(Place $p) => $p->isOnDuty());
        // Default range: today to the coming Sunday night, how rotas usually run.
        $from = now()->toDateString();
        $until = now()->endOfWeek()->toDateString();
        return view('placemanagement::admin.places.on-duty', compact('pharmacies', 'current', 'from', 'until'));
    }

    public function save(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'place_ids' => 'nullable|array',
            'place_ids.*' => 'uuid',
            'from' => 'required|date',
            'until' => 'required|date|after_or_equal:from',
        ]);
        $count = $this->places->setOnDuty($data['place_ids'] ?? [], $data['from'], $data['until']);
        Toastr::success($count
            ? translate('On-duty pharmacies saved') . ': ' . $count
            : translate('On-duty list cleared'));
        return back();
    }
}
