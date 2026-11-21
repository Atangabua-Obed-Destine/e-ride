<?php

namespace Modules\PlaceManagement\Lib;

use Illuminate\Support\Collection;
use Modules\PlaceManagement\Transformers\PlacePresenter;

/**
 * Which places are E-Ride partner stores (Food & Shops), so the app can offer
 * "Order" on them. A place matches a store when the team linked them
 * (places.store_id), or when the names agree and they are within 400 m of
 * each other. One query for the stores, then everything in memory.
 */
class PartnerStores
{
    private const NEAR_KM = 0.4;
    /** Words that say what kind of place it is, not which one. */
    private const NOISE = ['the', 'le', 'la', 'les', 'restaurant', 'resto', 'supermarket', 'supermarche', 'supermarché', 'shop', 'store',
        'boutique', 'lounge', 'bar', 'snack', 'cafe', 'café', 'bakery', 'boulangerie', 'and', 'et', 'bamenda', 'cameroon', 'cameroun'];

    private ?Collection $stores = null;

    private function stores(): Collection
    {
        if ($this->stores !== null) {
            return $this->stores;
        }
        if (!class_exists(\Modules\StoreManagement\Entities\Store::class) || !\Modules\StoreManagement\Lib\StoreSettings::enabled()) {
            return $this->stores = collect();
        }
        // Every active kitchen (a brand and its branches), under its active brand.
        return $this->stores = \Modules\StoreManagement\Entities\Store::with('parent')->where('is_active', 1)->get()
            ->filter(fn($s) => $s->brand()->is_active)
            ->map(fn($s) => ['store' => $s, 'key' => self::key($s->name)])
            ->values();
    }

    /** A name without accents, punctuation or words like "restaurant": "Romz Restaurant" and "ROMZ" agree. */
    public static function key(?string $name): string
    {
        $n = mb_strtolower((string)$name);
        $n = strtr($n, ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'à' => 'a', 'â' => 'a', 'ô' => 'o', 'î' => 'i', 'ï' => 'i', 'ç' => 'c', 'ù' => 'u', 'û' => 'u', '&' => ' and ']);
        $words = preg_split('/[^a-z0-9]+/', $n, -1, PREG_SPLIT_NO_EMPTY);
        $kept = array_values(array_diff($words, self::NOISE));
        return implode('', $kept ?: $words);
    }

    private static function sameName(string $a, string $b): bool
    {
        if ($a === '' || $b === '') {
            return false;
        }
        if ($a === $b) {
            return true;
        }
        [$short, $long] = strlen($a) <= strlen($b) ? [$a, $b] : [$b, $a];
        return strlen($short) >= 5 && str_contains($long, $short);
    }

    /** The partner store of a place card (PlacePresenter shape), or null. */
    public function of(array $card, ?string $linkedStoreId = null): ?array
    {
        $stores = $this->stores();
        if ($stores->isEmpty()) {
            return null;
        }
        $hit = null;
        if ($linkedStoreId) {
            $hit = $stores->first(fn($s) => $s['store']->id === $linkedStoreId || $s['store']->brand()->id === $linkedStoreId);
        }
        if (!$hit && isset($card['latitude'], $card['longitude'])) {
            $key = self::key($card['name'] ?? '');
            $best = null;
            foreach ($stores as $s) {
                $km = PlacePresenter::distance((float)$card['latitude'], (float)$card['longitude'], (float)$s['store']->latitude, (float)$s['store']->longitude);
                if ($km !== null && $km <= self::NEAR_KM && self::sameName($key, $s['key']) && ($best === null || $km < $best[1])) {
                    $best = [$s, $km];
                }
            }
            $hit = $best[0] ?? null;
        }
        if (!$hit) {
            return null;
        }
        $kitchen = $hit['store'];
        $brand = $kitchen->brand();
        return [
            // The app opens the brand; its menu call picks the branch that serves the customer.
            'id' => $brand->id,
            'name' => $brand->name,
            'type' => $brand->type,
            'open_now' => $kitchen->isOpenNow(),
            'logo' => $kitchen->logoUrl(),
        ];
    }
}
