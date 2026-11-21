<?php

namespace Modules\MarketManagement\Http\Controllers\Web\Admin;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\MarketManagement\Entities\MarketVenue;
use Modules\MarketManagement\Service\VenueService;

/** Admin, E-Ride Market > Markets: the markets shops can list themselves under. */
class MarketVenueAdminController extends Controller
{
    public function __construct(protected VenueService $venues)
    {
    }

    public function index(): View
    {
        $venues = MarketVenue::withCount(['liveShops as shops_count', 'liveShops as verified_count' => fn($q) => $q->whereNotNull('verified_at'),
            'liveShops as waiting_count' => fn($q) => $q->whereNull('verified_at')->whereNotNull('verification_requested_at')])
            ->orderBy('sort')->orderBy('name')->get();
        return view('marketmanagement::admin.venues', compact('venues'));
    }

    public function form(?MarketVenue $venue = null): View
    {
        return view('marketmanagement::admin.venue-form', ['venue' => $venue ?? new MarketVenue(['city' => 'Bamenda', 'is_active' => true])]);
    }

    public function save(Request $request, ?MarketVenue $venue = null): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|min:3|max:80',
            'city' => 'nullable|string|max:60',
            'area_label' => 'nullable|string|max:120',
            'about' => 'nullable|string|max:500',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'entrances' => 'nullable|string|max:2000',
            'blocks' => 'nullable|string|max:2000',
            'buildings' => 'nullable|string|max:2000',
            'is_active' => 'nullable|boolean',
            'sort' => 'nullable|integer|min:0|max:999',
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $this->venues->save($venue, $data);
        Toastr::success(translate('Market saved'));
        return redirect()->route('admin.market.venues');
    }
}
