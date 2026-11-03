<?php

namespace Modules\PlaceManagement\Http\Controllers\Web\Admin;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\PlaceManagement\Entities\GoogleHiddenPlace;
use Modules\PlaceManagement\Lib\GooglePlacesClient;
use Modules\PlaceManagement\Lib\PlaceSettings;
use Modules\PlaceManagement\Service\Interfaces\PlaceServiceInterface;

/** Admin: preview the Google results customers see, hide bad ones, turn good ones into verified listings. */
class GooglePlaceController extends Controller
{
    public function __construct(protected PlaceServiceInterface $places)
    {
    }

    public function index(Request $request): View
    {
        $data = $request->validate([
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);
        $lat = (float)($data['latitude'] ?? 5.9597);
        $lng = (float)($data['longitude'] ?? 10.1460);
        // A search costs money, so it only runs when the admin asks for it.
        $results = $request->boolean('load') ? $this->places->googlePreview($lat, $lng) : false;
        $hidden = GoogleHiddenPlace::with('author')->latest()->get();
        $settings = PlaceSettings::all();
        $usage = self::usage();
        return view('placemanagement::admin.places.google', compact('results', 'hidden', 'lat', 'lng', 'settings', 'usage'));
    }

    public function hide(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'google_place_id' => 'required|string|max:255|regex:/^[A-Za-z0-9_-]+$/',
            'label' => 'nullable|string|max:150',
            'reason' => 'nullable|string|max:255',
        ]);
        $this->places->hideGooglePlace($data['google_place_id'], $data['label'] ?? null, $data['reason'] ?? null, auth()->id());
        Toastr::success(translate('Hidden from the app'));
        return back();
    }

    public function unhide(Request $request): RedirectResponse
    {
        $data = $request->validate(['google_place_id' => 'required|string|max:255']);
        $this->places->unhideGooglePlace($data['google_place_id']);
        Toastr::success(translate('Shown in the app again'));
        return back();
    }

    public static function usage(): array
    {
        return [
            'search' => GooglePlacesClient::usage(GooglePlacesClient::SEARCH),
            'photo' => GooglePlacesClient::usage(GooglePlacesClient::PHOTO),
        ];
    }
}
