<?php

namespace Modules\PlaceManagement\Http\Controllers\Web\Admin;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\PlaceManagement\Entities\Place;
use Modules\PlaceManagement\Entities\PlaceCategory;

/** Admin: the Explore Bamenda categories, and which Google place types each one searches. */
class PlaceCategoryController extends Controller
{
    public function index(): View
    {
        $categories = PlaceCategory::orderBy('sort')->orderBy('name')->get();
        $counts = Place::selectRaw('explore_category, count(*) as n')->groupBy('explore_category')->pluck('n', 'explore_category');
        return view('placemanagement::admin.places.categories', compact('categories', 'counts'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $key = Str::slug($data['name'], '_');
        if ($key === '' || PlaceCategory::where('key', $key)->exists()) {
            $key .= '_' . Str::lower(Str::random(4));
        }
        PlaceCategory::create($data + ['key' => $key]);
        Toastr::success(translate('Category added'));
        return back();
    }

    public function update(Request $request, PlaceCategory $category): RedirectResponse
    {
        $category->update($this->validated($request));
        Toastr::success(translate('Category saved'));
        return back();
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'name_fr' => 'nullable|string|max:80',
            'icon' => ['required', Rule::in(PlaceCategory::ICONS)],
            // Google "Table A" types, comma separated, e.g. "pharmacy, drugstore".
            'google_types' => ['nullable', 'string', 'max:300', 'regex:/^[a-z_,\s]*$/'],
            'has_price' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'sort' => 'nullable|integer',
        ], [
            'google_types.regex' => translate('Use Google place types in lower case, separated by commas, e.g. pharmacy, drugstore.'),
        ]);
        $data['google_types'] = array_values(array_filter(array_map('trim', explode(',', (string)($data['google_types'] ?? '')))));
        $data['has_price'] = $request->boolean('has_price');
        $data['is_active'] = $request->boolean('is_active');
        $data['sort'] = (int)($data['sort'] ?? 0);
        return $data;
    }
}
