<?php

namespace Modules\PlaceManagement\Http\Controllers\Web\Admin;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\PlaceManagement\Entities\Place;
use Modules\PlaceManagement\Entities\PlaceCategory;
use Modules\PlaceManagement\Http\Requests\PlaceSaveRequest;
use Modules\PlaceManagement\Service\Interfaces\PlaceServiceInterface;

/** Admin: E-Ride's verified places to stay. */
class PlaceController extends Controller
{
    public function __construct(protected PlaceServiceInterface $places)
    {
    }

    public function index(Request $request): View
    {
        $filters = [
            'search' => (string)$request->query('search', ''),
            'explore' => (string)$request->query('explore', 'all'),
            'status' => (string)$request->query('status', 'all'),
        ];
        $places = $this->places->list($filters);
        $categories = PlaceCategory::orderBy('sort')->orderBy('name')->get();
        return view('placemanagement::admin.places.index', compact('places', 'filters', 'categories'));
    }

    /** Prefilled when coming from the Google results page ("Make a verified listing"). */
    public function create(Request $request): View
    {
        $explore = PlaceCategory::where('key', $request->query('explore', 'stay'))->value('key') ?? 'stay';
        $types = PlaceCategory::typesFor($explore);
        $place = new Place([
            'explore_category' => $explore,
            'category' => in_array($request->query('category'), $types, true) ? $request->query('category') : $types[0],
            'is_active' => true,
            'name' => $request->query('name'),
            'address' => $request->query('address'),
            'latitude' => $request->query('latitude'),
            'longitude' => $request->query('longitude'),
            'phone' => $request->query('phone'),
            'website' => $request->query('website'),
            'google_place_id' => $request->query('google_place_id'),
            'check_in' => '14:00',
            'check_out' => '11:00',
        ]);
        return $this->form($place);
    }

    public function store(PlaceSaveRequest $request): RedirectResponse
    {
        $place = $this->places->save(new Place(), $request->validated());
        $this->saved($place, translate('Listing created'));
        return redirect()->route('admin.place.edit', $place);
    }

    public function edit(Place $place): View
    {
        return $this->form($place);
    }

    private function form(Place $place): View
    {
        $categories = PlaceCategory::orderBy('sort')->orderBy('name')->get();
        return view('placemanagement::admin.places.form', compact('place', 'categories'));
    }

    public function update(PlaceSaveRequest $request, Place $place): RedirectResponse
    {
        $this->places->save($place, $request->validated());
        $this->saved($place, translate('Listing updated'));
        return back();
    }

    public function status(Request $request, Place $place): RedirectResponse
    {
        $place->update(['is_active' => $request->boolean('is_active')]);
        Toastr::success($place->is_active ? translate('Listing shown in the app') : translate('Listing hidden from the app'));
        return back();
    }

    public function destroy(Place $place): RedirectResponse
    {
        foreach ($place->photos ?? [] as $file) {
            fileRemover('place/photo/', $file);
        }
        $place->update(['photos' => []]);
        $place->delete();
        Toastr::success(translate('Listing removed'));
        return redirect()->route('admin.place.index');
    }

    private function saved(Place $place, string $message): void
    {
        Toastr::success($message);
        if (empty($place->photos)) {
            Toastr::warning(translate('Add a few real photos. Listings with photos get far more taps.'));
        }
        if (!$place->zone_id) {
            Toastr::warning(translate('This location is outside every active zone, so customers cannot book a ride there yet.'));
        }
    }
}
