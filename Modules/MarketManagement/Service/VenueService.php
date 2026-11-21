<?php

namespace Modules\MarketManagement\Service;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\MarketManagement\Entities\MarketItem;
use Modules\MarketManagement\Entities\MarketShop;
use Modules\MarketManagement\Entities\MarketVenue;
use Modules\MarketManagement\Lib\MarketTags;

/**
 * Markets and the sheds inside them. E-Ride creates the markets; any shop can
 * list itself under one with its shed. Buyers search a market for what they
 * need and walk to the shed.
 */
class VenueService
{
    /** Markets for buyers, nearest first when we know where they are. */
    public function list(?float $lat = null, ?float $lng = null): Collection
    {
        $venues = MarketVenue::where('is_active', true)
            ->withCount(['liveShops as shops_count', 'liveShops as verified_count' => fn($q) => $q->whereNotNull('verified_at')])
            ->orderBy('sort')->orderBy('name')->get();
        if ($lat !== null && $lng !== null) {
            $venues->each(fn(MarketVenue $v) => $v->distance_km = round(self::km($lat, $lng, $v->latitude, $v->longitude), 1));
            $venues = $venues->sortBy('distance_km')->values();
        }
        return $venues;
    }

    /**
     * The shops of a market, verified first. With a search: shops whose name, shed,
     * block or "what we sell" matches, or that have a matching item (up to three
     * are named, so the buyer sees why).
     *
     * @return array{shops: Collection, matches: array<string, string[]>, searched: bool}
     */
    public function shops(MarketVenue $venue, string $query = '', bool $verifiedOnly = false, ?string $block = null, ?string $building = null): array
    {
        $base = $venue->liveShops()->with('venue')
            ->when($verifiedOnly, fn($q) => $q->whereNotNull('verified_at'))
            ->when($block, fn($q) => $q->where('shed_block', $block))
            ->when($building, fn($q) => $q->where('shed_building', $building));
        $words = $this->terms($query);
        $matches = [];
        if ($words) {
            $itemHits = MarketItem::whereIn('shop_id', (clone $base)->pluck('id'))->whereIn('status', MarketItem::PUBLIC)
                ->where(function ($q) use ($words) {
                    foreach ($words as $w) {
                        $q->orWhere('title', 'like', '%' . $w . '%')->orWhere('tags_search', 'like', '%' . $w . '%');
                    }
                })->get(['shop_id', 'title']);
            foreach ($itemHits as $hit) {
                if (count($matches[$hit->shop_id] ?? []) < 3) {
                    $matches[$hit->shop_id][] = $hit->title;
                }
            }
            $base->where(function ($q) use ($words, $matches) {
                foreach ($words as $w) {
                    $q->orWhere('name', 'like', '%' . $w . '%')
                        ->orWhere('shed_number', $w)
                        ->orWhere('shed_block', 'like', '%' . $w . '%')
                        ->orWhere('shed_building', 'like', '%' . $w . '%')
                        ->orWhere('sells_search', 'like', '%' . $w . '%')
                        ->orWhere('categories', 'like', '%"' . $w . '%');
                }
                if ($matches) {
                    $q->orWhereIn('id', array_keys($matches));
                }
            });
        }
        $shops = $base->orderByRaw('verified_at IS NULL')->orderBy('shed_building')->orderBy('shed_floor')->orderBy('shed_block')->orderBy('shed_number')->orderBy('name')->limit(200)->get();
        if ($words) {
            MarketTags::record($query, $shops->isNotEmpty());
        }
        return ['shops' => $shops, 'matches' => $matches, 'searched' => (bool)$words];
    }

    /** Words of a search, normalised like tags ("Chaussures " -> "chaussures"). */
    private function terms(string $query): array
    {
        $query = trim(mb_strtolower($query));
        if (mb_strlen($query) < 2) {
            return [];
        }
        $words = array_filter(array_map('trim', preg_split('/[\s,]+/u', $query)), fn($w) => mb_strlen($w) >= 2);
        // The whole phrase too ("school shoes"), so a tag of two words matches.
        if (count($words) > 1) {
            array_unshift($words, $query);
        }
        return array_values(array_unique(array_slice($words, 0, 6)));
    }

    /** "school shoes, Bags" -> ["school shoes", "bags"]: the shop's words for what it sells. */
    public static function sells(mixed $raw): array
    {
        $list = is_array($raw) ? $raw : (json_decode((string)$raw, true) ?: preg_split('/[,;\n]+/u', (string)$raw));
        $out = [];
        foreach ((array)$list as $t) {
            if (is_scalar($t) && ($c = MarketTags::clean((string)$t)) !== null && !in_array($c, $out, true)) {
                $out[] = $c;
            }
        }
        return array_slice($out, 0, MarketShop::MAX_SELLS);
    }

    // ------------------------------------------------------------ admin

    public function save(?MarketVenue $venue, array $data): MarketVenue
    {
        $venue ??= new MarketVenue();
        $venue->fill([
            'name' => trim($data['name']),
            'city' => trim($data['city'] ?? 'Bamenda') ?: 'Bamenda',
            'area_label' => $data['area_label'] ?? null,
            'about' => $data['about'] ?? null,
            'latitude' => (float)$data['latitude'],
            'longitude' => (float)$data['longitude'],
            'entrances' => $this->parseEntrances((string)($data['entrances'] ?? '')),
            'blocks' => array_values(array_filter(array_map('trim', preg_split('/[\n,]+/u', (string)($data['blocks'] ?? ''))))),
            'buildings' => $this->parseBuildings((string)($data['buildings'] ?? '')),
            'is_active' => (bool)($data['is_active'] ?? true),
            'sort' => (int)($data['sort'] ?? 0),
        ]);
        if (!$venue->slug) {
            $base = Str::limit(Str::slug($venue->name), 80, '') ?: 'market';
            $slug = $base;
            while (MarketVenue::where('slug', $slug)->exists()) {
                $slug = $base . '-' . strtolower(Str::random(3));
            }
            $venue->slug = $slug;
        }
        $venue->save();
        return $venue;
    }

    /** One building per line: "Main hall | 3" (3 floors with the ground floor), or just "Main hall". */
    private function parseBuildings(string $text): ?array
    {
        $out = [];
        foreach (preg_split('/\n+/', $text) as $line) {
            $parts = array_map('trim', explode('|', $line));
            if (($parts[0] ?? '') === '') {
                continue;
            }
            $out[] = ['name' => mb_substr($parts[0], 0, 60), 'floors' => max(1, min(30, (int)($parts[1] ?? 1) ?: 1))];
        }
        return $out ?: null;
    }

    /** One gate per line: "Main gate | 5.9601, 10.1512". */
    private function parseEntrances(string $text): ?array
    {
        $out = [];
        foreach (preg_split('/\n+/', $text) as $line) {
            if (!preg_match('/^\s*(.*?)\s*\|\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/', $line, $m)) {
                if (trim($line) !== '') {
                    throw ValidationException::withMessages(['entrances' => translate('Write each gate as: Name | latitude, longitude')]);
                }
                continue;
            }
            $out[] = ['name' => $m[1], 'lat' => (float)$m[2], 'lng' => (float)$m[3]];
        }
        return $out ?: null;
    }

    public static function km(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return 2 * $r * asin(min(1, sqrt($a)));
    }
}
