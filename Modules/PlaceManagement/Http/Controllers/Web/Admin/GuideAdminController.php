<?php

namespace Modules\PlaceManagement\Http\Controllers\Web\Admin;

use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\AssistantManagement\Service\AssistantService;
use Modules\PlaceManagement\Entities\Place;
use Modules\PlaceManagement\Entities\PlaceCategory;
use Modules\PlaceManagement\Entities\PlaceRating;
use Modules\PlaceManagement\Entities\Tour;
use Modules\PlaceManagement\Entities\TourStop;
use Modules\PlaceManagement\Service\GuideService;
use Modules\ZoneManagement\Entities\Zone;

/**
 * Admin: the city and tour guide. AI drafts of place guides and tours (the
 * team checks and publishes), the tour builder, and riders' ratings.
 */
class GuideAdminController extends Controller
{
    private const STYLE = 'Write in plain, warm words for visitors and locals. Short sentences. Never use em dashes. '
        . 'Only state facts that are widely known about the place; never invent prices, opening hours, phone numbers, dates or history. '
        . 'When you are not sure of something, stay general (for example "ask at the gate about the entry fee"). '
        . 'The French must be natural French as spoken in Cameroon, not a word-for-word translation.';

    public function __construct(protected GuideService $guide)
    {
    }

    // ------------------------------------------------------------------ place guide draft

    public function placeDraft(Request $request, AssistantService $ai): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'explore_category' => 'nullable|string|max:40',
            'category' => 'nullable|string|max:40',
            'address' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1500',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ]);
        $city = filled($data['latitude'] ?? null) && filled($data['longitude'] ?? null)
            ? $this->guide->zoneAt((float)$data['latitude'], (float)$data['longitude'])?->name : null;
        $kind = PlaceCategory::where('key', $data['explore_category'] ?? '')->value('name');

        $system = 'You write the visitor guide of one place for E-Ride, a ride and city guide app in Cameroon. ' . self::STYLE
            . ' Answer with JSON only, exactly these keys: {"story_en": "2 to 4 sentences: what it is and why go", "story_fr": "...", '
            . '"best_time_en": "one short line", "best_time_fr": "...", "tips_en": "2 or 3 practical tips, one per line", "tips_fr": "...", '
            . '"stay_minutes": number (a usual visit length)}';
        $prompt = "Place: {$data['name']}\n"
            . ($kind ? "Explore category: {$kind}\n" : '')
            . (filled($data['category'] ?? null) ? "Type: {$data['category']}\n" : '')
            . (filled($data['address'] ?? null) ? "Address: {$data['address']}\n" : '')
            . ($city ? "E-Ride service area: {$city}\n" : '')
            . (filled($data['description'] ?? null) ? "What the team wrote: {$data['description']}\n" : '');

        $draft = $ai->draftJson($system, $prompt);
        if (!$draft) {
            return response()->json(['message' => translate('The AI is not available right now. Check Ask E-Ride\'s AI settings, or write the guide yourself.')], 503);
        }
        $minutes = (int)($draft['stay_minutes'] ?? 0);
        return response()->json([
            'message' => translate('Draft written. Check every fact with the place, then set the status to Checked and published.'),
            'draft' => [
                'story' => self::clean($draft['story_en'] ?? null, 2000),
                'story_fr' => self::clean($draft['story_fr'] ?? null, 2000),
                'best_time' => self::clean($draft['best_time_en'] ?? null, 200),
                'best_time_fr' => self::clean($draft['best_time_fr'] ?? null, 200),
                'tips' => self::clean($draft['tips_en'] ?? null, 1000),
                'tips_fr' => self::clean($draft['tips_fr'] ?? null, 1000),
                'stay_minutes' => $minutes >= 5 && $minutes <= 600 ? (int)(round($minutes / 5) * 5) : null,
            ],
        ]);
    }

    private static function clean(mixed $value, int $max): ?string
    {
        if (is_array($value)) {
            $value = implode("\n", array_map('strval', $value));
        }
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        // House style: no em dashes.
        $value = str_replace(["\u{2014}", "\u{2013}"], [', ', '-'], trim($value));
        return mb_substr($value, 0, $max);
    }

    // ------------------------------------------------------------------ tours

    public function tours(Request $request): View
    {
        $zoneId = (string)$request->query('zone', '');
        $tours = Tour::with(['zone', 'stops.place'])
            ->when($zoneId !== '', fn($q) => $q->where('zone_id', $zoneId))
            ->orderBy('zone_id')->orderByDesc('is_featured')->orderBy('sort')->orderBy('title')->get();
        $zones = Zone::orderBy('name')->get(['id', 'name', 'is_active']);
        $cards = $tours->mapWithKeys(fn(Tour $t) => [$t->id => $this->guide->tourCard($t, 'en')]);
        return view('placemanagement::admin.places.tours', compact('tours', 'zones', 'zoneId', 'cards'));
    }

    public function createTour(Request $request): View
    {
        return $this->tourForm(new Tour(['status' => Tour::DRAFT, 'zone_id' => $request->query('zone')]));
    }

    public function editTour(Tour $tour): View
    {
        return $this->tourForm($tour->load('stops.place'));
    }

    private function tourForm(Tour $tour): View
    {
        $zones = Zone::where('is_active', 1)->orderBy('name')->get(['id', 'name']);
        // Stops come from verified, active places: Things to do first, then anything else.
        $places = Place::where('is_active', 1)->orderBy('name')
            ->get(['id', 'name', 'explore_category', 'category', 'address', 'latitude', 'longitude', 'zone_id', 'guide_status', 'photos']);
        $things = PlaceCategory::THINGS;
        return view('placemanagement::admin.places.tour-form', compact('tour', 'zones', 'places', 'things'));
    }

    public function storeTour(Request $request): RedirectResponse
    {
        $tour = $this->saveTour(new Tour(['created_by' => auth()->id()]), $request);
        Toastr::success(translate('Tour saved'));
        return redirect()->route('admin.place.tours.edit', $tour);
    }

    public function updateTour(Request $request, Tour $tour): RedirectResponse
    {
        $this->saveTour($tour, $request);
        Toastr::success(translate('Tour saved'));
        return back();
    }

    private function saveTour(Tour $tour, Request $request): Tour
    {
        $data = $request->validate([
            'zone_id' => ['required', Rule::exists('zones', 'id')],
            'title' => 'required|string|max:120',
            'title_fr' => 'nullable|string|max:120',
            'intro' => 'nullable|string|max:1500',
            'intro_fr' => 'nullable|string|max:1500',
            'status' => ['required', Rule::in(Tour::STATUSES)],
            'is_featured' => 'nullable|boolean',
            'sort' => 'nullable|integer',
            'stops' => 'nullable|array|max:' . Tour::MAX_STOPS,
            'stops.*.place_id' => ['required', 'distinct', Rule::exists('places', 'id')->whereNull('deleted_at')],
            'stops.*.note' => 'nullable|string|max:400',
            'stops.*.note_fr' => 'nullable|string|max:400',
            'stops.*.stay_minutes' => 'nullable|integer|min:5|max:600',
        ], [
            'stops.*.place_id.distinct' => translate('A place can be only once in a tour.'),
        ]);
        $stops = array_values($data['stops'] ?? []);
        if ($data['status'] === Tour::PUBLISHED && count($stops) < 1) {
            abort(back()->withInput()->withErrors(['stops' => translate('Add at least one stop before publishing.')]));
        }
        DB::transaction(function () use ($tour, $data, $stops) {
            $wasPublished = $tour->status === Tour::PUBLISHED;
            $tour->fill([
                'zone_id' => $data['zone_id'],
                'title' => trim($data['title']),
                'title_fr' => filled($data['title_fr'] ?? null) ? trim($data['title_fr']) : null,
                'intro' => filled($data['intro'] ?? null) ? trim($data['intro']) : null,
                'intro_fr' => filled($data['intro_fr'] ?? null) ? trim($data['intro_fr']) : null,
                'status' => $data['status'],
                'is_featured' => (bool)($data['is_featured'] ?? false),
                'sort' => (int)($data['sort'] ?? 0),
            ]);
            if (!$tour->slug || $tour->isDirty('title')) {
                $tour->slug = GuideService::slugFor($tour->title, $tour->id);
            }
            if ($data['status'] === Tour::PUBLISHED && !$wasPublished) {
                $tour->published_at = now();
            }
            $tour->save();
            TourStop::where('tour_id', $tour->id)->delete();
            foreach ($stops as $i => $s) {
                TourStop::create([
                    'tour_id' => $tour->id,
                    'place_id' => $s['place_id'],
                    'position' => $i + 1,
                    'note' => filled($s['note'] ?? null) ? trim($s['note']) : null,
                    'note_fr' => filled($s['note_fr'] ?? null) ? trim($s['note_fr']) : null,
                    'stay_minutes' => filled($s['stay_minutes'] ?? null) ? (int)$s['stay_minutes'] : null,
                ]);
            }
        });
        $unchecked = Place::whereIn('id', array_column($stops, 'place_id'))->where('guide_status', '!=', Place::GUIDE_PUBLISHED)->count();
        if ($unchecked) {
            Toastr::warning(translate('Some stops have no published guide yet. Riders will see only their name, photos and ratings there.'));
        }
        return $tour;
    }

    public function tourStatus(Request $request, Tour $tour): RedirectResponse
    {
        $status = $request->validate(['status' => ['required', Rule::in(Tour::STATUSES)]])['status'];
        if ($status === Tour::PUBLISHED && !$tour->stops()->exists()) {
            Toastr::error(translate('Add at least one stop before publishing.'));
            return back();
        }
        $tour->update(['status' => $status, 'published_at' => $status === Tour::PUBLISHED ? ($tour->published_at ?? now()) : $tour->published_at]);
        Toastr::success(translate('Tour updated'));
        return back();
    }

    public function destroyTour(Tour $tour): RedirectResponse
    {
        $tour->delete();
        Toastr::success(translate('Tour removed'));
        return redirect()->route('admin.place.tours');
    }

    /** AI: a title, an introduction and a note per stop, for the stops the team picked. */
    public function tourDraft(Request $request, AssistantService $ai): JsonResponse
    {
        $data = $request->validate([
            'zone_id' => 'nullable|string',
            'place_ids' => 'required|array|min:1|max:' . Tour::MAX_STOPS,
            'place_ids.*' => 'string',
        ]);
        $places = Place::whereIn('id', $data['place_ids'])->get()->keyBy('id');
        $ordered = collect($data['place_ids'])->map(fn($id) => $places->get($id))->filter()->values();
        if ($ordered->isEmpty()) {
            return response()->json(['message' => translate('Pick the stops first.')], 422);
        }
        $city = filled($data['zone_id'] ?? null) ? Zone::where('id', $data['zone_id'])->value('name') : null;
        $lines = $ordered->map(fn(Place $p, $i) => ($i + 1) . '. ' . $p->name . ' (' . $p->category . ($p->address ? ', ' . $p->address : '') . ')'
            . ($p->story ? ' Guide: ' . mb_substr($p->story, 0, 300) : ''))->implode("\n");
        $system = 'You write a self-guided city tour for E-Ride, a ride and city guide app in Cameroon. Each stop is reached by an E-Ride ride. ' . self::STYLE
            . ' Answer with JSON only: {"title_en": "short and inviting, max 50 characters", "title_fr": "...", '
            . '"intro_en": "2 or 3 sentences: who it is for and what they will see", "intro_fr": "...", '
            . '"stops": [{"note_en": "one sentence: what to do at this stop on this tour", "note_fr": "..."}] (one per stop, same order)}';
        $prompt = ($city ? "Service area: {$city}\n" : '') . "Stops in order:\n" . $lines;
        $draft = $ai->draftJson($system, $prompt);
        if (!$draft) {
            return response()->json(['message' => translate('The AI is not available right now. Check Ask E-Ride\'s AI settings, or write the tour yourself.')], 503);
        }
        $notes = array_values(is_array($draft['stops'] ?? null) ? $draft['stops'] : []);
        return response()->json([
            'message' => translate('Draft written. Check it, then publish the tour when it is right.'),
            'draft' => [
                'title' => self::clean($draft['title_en'] ?? null, 120),
                'title_fr' => self::clean($draft['title_fr'] ?? null, 120),
                'intro' => self::clean($draft['intro_en'] ?? null, 1500),
                'intro_fr' => self::clean($draft['intro_fr'] ?? null, 1500),
                'notes' => $ordered->map(fn($p, $i) => [
                    'note' => self::clean($notes[$i]['note_en'] ?? null, 400),
                    'note_fr' => self::clean($notes[$i]['note_fr'] ?? null, 400),
                ])->values()->all(),
            ],
        ]);
    }

    // ------------------------------------------------------------------ ratings

    public function ratings(Request $request): View
    {
        $filter = (string)$request->query('show', 'all');
        $search = trim((string)$request->query('search', ''));
        $ratings = PlaceRating::with('user:id,first_name,last_name,phone')
            ->when($filter === 'hidden', fn($q) => $q->where('is_visible', 0))
            ->when($filter === 'low', fn($q) => $q->where('stars', '<=', 2))
            ->when($filter === 'written', fn($q) => $q->whereNotNull('comment'))
            ->when($search !== '', fn($q) => $q->where(fn($w) => $w->where('place_name', 'like', "%{$search}%")->orWhere('comment', 'like', "%{$search}%")))
            ->orderByDesc('updated_at')->paginate(30)->appends(['show' => $filter, 'search' => $search]);
        return view('placemanagement::admin.places.ratings', compact('ratings', 'filter', 'search'));
    }

    public function ratingVisibility(Request $request, PlaceRating $rating): RedirectResponse
    {
        $data = $request->validate(['visible' => 'required|boolean', 'reason' => 'nullable|string|max:150']);
        $rating->update(['is_visible' => (bool)$data['visible'], 'hidden_reason' => $data['visible'] ? null : ($data['reason'] ?? null)]);
        Toastr::success($rating->is_visible ? translate('Rating shown again') : translate('Rating hidden from the app'));
        return back();
    }
}
