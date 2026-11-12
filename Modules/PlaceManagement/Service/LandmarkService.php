<?php

namespace Modules\PlaceManagement\Service;

use Illuminate\Support\Collection;
use MatanYadaev\EloquentSpatial\Objects\Point;
use Modules\PlaceManagement\Entities\Landmark;
use Modules\PlaceManagement\Entities\Place;
use Modules\PlaceManagement\Lib\LocalSettings;
use Modules\UserManagement\Entities\UserAddress;
use Modules\ZoneManagement\Entities\Zone;

/**
 * Landmarks in the place search. The app already reads Google's autocomplete
 * shape (data.suggestions[].placePrediction), so landmarks and the rider's
 * saved places are sent in that same shape, ahead of Google's results, with a
 * placeId the details call recognises: "landmark:<uuid>" or "saved:<id>".
 */
class LandmarkService
{
    public const LANDMARK = 'landmark:';
    public const SAVED = 'saved:';

    /** Saved places, then landmarks, matching what was typed. */
    public function suggestions(string $text, ?string $userId): array
    {
        $text = trim($text);
        if (mb_strlen($text) < 2 || !LocalSettings::landmarks()) {
            return [];
        }
        $out = [];
        foreach ($this->saved($userId, $text, 3) as $address) {
            $out[] = $this->savedSuggestion($address);
        }
        foreach ($this->search($text, LocalSettings::all()['search_results']) as $landmark) {
            $out[] = $this->landmarkSuggestion($landmark);
        }
        return $out;
    }

    /** Nothing typed yet: the rider's saved places and the most used landmarks. */
    public function startList(?string $userId): array
    {
        if (!LocalSettings::landmarks()) {
            return [];
        }
        $out = [];
        foreach ($this->saved($userId, null, 5) as $address) {
            $out[] = $this->savedSuggestion($address);
        }
        if (($n = LocalSettings::all()['popular_results']) > 0) {
            foreach (Landmark::where('is_active', 1)->orderByDesc('uses')->orderBy('name')->limit($n)->get() as $landmark) {
                $out[] = $this->landmarkSuggestion($landmark);
            }
        }
        return $out;
    }

    /** @return Collection<Landmark> names starting with the text first, then other matches, most used first */
    public function search(string $text, int $limit): Collection
    {
        $like = '%' . addcslashes($text, '%_\\') . '%';
        $starts = addcslashes($text, '%_\\') . '%';
        return Landmark::where('is_active', 1)
            ->where(fn($q) => $q->where('name', 'like', $like)->orWhere('aliases', 'like', $like)->orWhere('area', 'like', $like))
            ->orderByRaw('CASE WHEN name LIKE ? THEN 0 WHEN aliases LIKE ? THEN 1 ELSE 2 END', [$starts, $like])
            ->orderByDesc('uses')->orderBy('name')
            ->limit($limit)->get();
    }

    /** The details call for a landmark or saved place, in Google's place-details shape. Null if not one of ours. */
    public function details(string $placeId): ?array
    {
        if (str_starts_with($placeId, self::LANDMARK)) {
            $landmark = Landmark::where('is_active', 1)->find(substr($placeId, strlen(self::LANDMARK)));
            if (!$landmark) {
                return null;
            }
            $landmark->increment('uses');
            return $this->place($placeId, $landmark->fullName(), $landmark->name, $landmark->latitude, $landmark->longitude);
        }
        if (str_starts_with($placeId, self::SAVED)) {
            $address = UserAddress::where('user_id', auth('api')->id())->find(substr($placeId, strlen(self::SAVED)));
            if (!$address) {
                return null;
            }
            return $this->place($placeId, $address->address, $address->address_label ?: $address->address, (float)$address->latitude, (float)$address->longitude);
        }
        return null;
    }

    public function isOurs(string $placeId): bool
    {
        return str_starts_with($placeId, self::LANDMARK) || str_starts_with($placeId, self::SAVED);
    }

    /** Every active Explore Bamenda place not imported yet becomes a landmark. Returns how many. */
    public function importPlaces(): int
    {
        $done = Landmark::whereNotNull('place_id')->pluck('place_id')->all();
        $count = 0;
        foreach (Place::where('is_active', 1)->whereNotIn('id', $done)->get() as $place) {
            Landmark::create([
                'name' => mb_substr($place->name, 0, 120),
                'area' => $this->areaOf($place->address),
                'latitude' => $place->latitude,
                'longitude' => $place->longitude,
                'zone_id' => $place->zone_id ?: $this->zoneAt($place->latitude, $place->longitude),
                'place_id' => $place->id,
                'is_active' => 1,
            ]);
            $count++;
        }
        return $count;
    }

    public function zoneAt(float $lat, float $lng): ?string
    {
        try {
            return Zone::whereContains('coordinates', new Point($lat, $lng))->where('is_active', 1)->value('id');
        } catch (\Throwable) {
            return null;
        }
    }

    /** "X4JH+57 Bamenda, Cameroon" → null; "Commercial Avenue, Bamenda, Cameroon" → "Commercial Avenue". */
    protected function areaOf(?string $address): ?string
    {
        $first = trim(explode(',', (string)$address)[0] ?? '');
        if ($first === '' || preg_match('/^[A-Z0-9]{4}\+[A-Z0-9]{2,3}/', $first)) {
            return null;
        }
        return mb_substr($first, 0, 120);
    }

    protected function saved(?string $userId, ?string $text, int $limit): Collection
    {
        if (!$userId) {
            return collect();
        }
        $q = UserAddress::where('user_id', $userId);
        if ($text !== null) {
            $like = '%' . addcslashes($text, '%_\\') . '%';
            $q->where(fn($w) => $w->where('address_label', 'like', $like)->orWhere('address', 'like', $like));
        }
        return $q->latest()->limit($limit)->get();
    }

    protected function savedSuggestion(UserAddress $address): array
    {
        $label = in_array($address->address_label, ['home', 'office', 'others'], true) ? translate($address->address_label) : $address->address_label;
        return $this->suggestion(self::SAVED . $address->id, $label ?: $address->address, $address->address, 'saved_place');
    }

    protected function landmarkSuggestion(Landmark $landmark): array
    {
        return $this->suggestion(self::LANDMARK . $landmark->id, $landmark->name, $landmark->area ?? '', 'landmark');
    }

    /** One entry of Google's autocomplete (New) "suggestions" list. */
    protected function suggestion(string $placeId, string $main, string $secondary, string $type): array
    {
        $text = $secondary !== '' && $secondary !== $main ? $main . ', ' . $secondary : $main;
        return ['placePrediction' => [
            'place' => 'places/' . $placeId,
            'placeId' => $placeId,
            'text' => ['text' => $text, 'matches' => []],
            'structuredFormat' => ['mainText' => ['text' => $main], 'secondaryText' => ['text' => $secondary]],
            'types' => [$type],
        ]];
    }

    /** Google's place-details shape, as far as the apps read it. */
    protected function place(string $placeId, string $address, string $name, float $lat, float $lng): array
    {
        return [
            'id' => $placeId,
            'name' => 'places/' . $placeId,
            'formattedAddress' => $address,
            'shortFormattedAddress' => $address,
            'displayName' => ['text' => $name, 'languageCode' => app()->getLocale()],
            'location' => ['latitude' => $lat, 'longitude' => $lng],
            'types' => [str_starts_with($placeId, self::LANDMARK) ? 'landmark' : 'saved_place'],
        ];
    }
}
