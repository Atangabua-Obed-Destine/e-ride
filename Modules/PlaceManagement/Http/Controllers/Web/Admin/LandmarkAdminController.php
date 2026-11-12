<?php

namespace Modules\PlaceManagement\Http\Controllers\Web\Admin;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Modules\PlaceManagement\Entities\DropFare;
use Modules\PlaceManagement\Entities\Landmark;
use Modules\PlaceManagement\Lib\LocalSettings;
use Modules\PlaceManagement\Service\LandmarkService;

/** Landmarks: the places riders find first in the app's place search. */
class LandmarkAdminController extends Controller
{
    public function __construct(protected LandmarkService $landmarks)
    {
    }

    public function index(Request $request): View
    {
        $q = trim((string)$request->query('q', ''));
        $list = Landmark::query()
            ->when($q !== '', fn($w) => $w->where(fn($x) => $x->where('name', 'like', "%{$q}%")->orWhere('aliases', 'like', "%{$q}%")->orWhere('area', 'like', "%{$q}%")))
            ->when($request->query('show') === 'hidden', fn($w) => $w->where('is_active', 0), fn($w) => $w->where('is_active', 1))
            ->orderByDesc('uses')->orderBy('name')->paginate(30)->withQueryString();
        $edit = $request->query('edit') ? Landmark::find($request->query('edit')) : null;
        $counts = ['active' => Landmark::where('is_active', 1)->count(), 'hidden' => Landmark::where('is_active', 0)->count()];
        $mapPoints = Landmark::where('is_active', 1)->get(['id', 'name', 'latitude', 'longitude'])
            ->map(fn($l) => ['id' => $l->id, 'name' => $l->name, 'lat' => $l->latitude, 'lng' => $l->longitude])->values();
        $settings = LocalSettings::all();
        return view('placemanagement::admin.landmarks.index', compact('list', 'edit', 'counts', 'mapPoints', 'settings', 'q'));
    }

    public function store(Request $request): RedirectResponse
    {
        Landmark::create($this->validated($request) + ['is_active' => 1]);
        Toastr::success(translate('Landmark added'));
        return redirect()->route('admin.landmark.index');
    }

    public function update(Request $request, Landmark $landmark): RedirectResponse
    {
        $landmark->update($this->validated($request));
        Toastr::success(translate('Landmark saved'));
        return redirect()->route('admin.landmark.index');
    }

    /** Hide or show again. A hidden landmark leaves the search and its drop routes stop applying. */
    public function toggle(Landmark $landmark): RedirectResponse
    {
        $landmark->update(['is_active' => !$landmark->is_active]);
        Toastr::success($landmark->is_active ? translate('Landmark shown in the search') : translate('Landmark hidden'));
        return back();
    }

    public function destroy(Landmark $landmark): RedirectResponse
    {
        if (DropFare::where('from_landmark_id', $landmark->id)->orWhere('to_landmark_id', $landmark->id)->exists()) {
            Toastr::error(translate('This landmark is used by a drop fare. Remove the drop fare first, or hide the landmark.'));
            return back();
        }
        $landmark->delete();
        Toastr::success(translate('Landmark deleted'));
        return back();
    }

    /** Every Explore Bamenda verified place becomes a landmark (once). */
    public function import(): RedirectResponse
    {
        $n = $this->landmarks->importPlaces();
        Toastr::success(translate(key: '{count} places added as landmarks', replace: ['count' => $n]));
        return back();
    }

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'landmarks_enabled' => 'nullable|boolean',
            'landmarks_search_results' => 'required|integer|min:1|max:8',
            'landmarks_popular_results' => 'required|integer|min:0|max:12',
        ]);
        $data['landmarks_enabled'] = $request->boolean('landmarks_enabled') ? 1 : 0;
        foreach ($data as $key => $value) {
            updateBusinessSetting(keyName: $key, settingType: LocalSettings::TYPE, value: (string)$value);
        }
        Cache::forget(CACHE_BUSINESS_SETTINGS);
        Toastr::success(translate('Landmark settings saved'));
        return back();
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'aliases' => 'nullable|string|max:2000',
            'area' => 'nullable|string|max:120',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);
        $data['zone_id'] = $this->landmarks->zoneAt((float)$data['latitude'], (float)$data['longitude']);
        return $data;
    }
}
