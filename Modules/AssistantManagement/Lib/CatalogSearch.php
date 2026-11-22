<?php

namespace Modules\AssistantManagement\Lib;

use Illuminate\Support\Str;
use Throwable;

/**
 * What E-Ride really has for a question: Market items and services, Market
 * shops, Food & Shops stores and their dishes, E-Ride's checked places and
 * Explore categories, tours, jobs, homes to rent and bus destinations.
 *
 * Ask E-Ride gives these to the model (so it suggests real things, never
 * invented ones) and the app shows them as cards that open the thing itself.
 * Database only: no Google call, no cost.
 */
class CatalogSearch
{
    public const MAX_RESULTS = 8;
    private const PER_SOURCE = 4;

    /** Words that say "I am looking for" rather than what, in English, French and Pidgin. */
    private const STOP = [
        // en
        'i', 'im', 'am', 'me', 'my', 'a', 'an', 'the', 'is', 'are', 'there', 'any', 'some', 'for', 'to', 'of', 'in', 'on', 'at', 'and', 'or',
        'want', 'need', 'looking', 'look', 'find', 'search', 'searching', 'buy', 'get', 'where', 'can', 'could', 'do', 'does', 'you', 'have',
        'has', 'sell', 'sells', 'selling', 'who', 'what', 'which', 'how', 'much', 'price', 'cost', 'near', 'nearby', 'around', 'here', 'please',
        'good', 'best', 'cheap', 'new', 'used', 'one', 'with', 'like', 'would', 'should', 'go', 'show', 'tell', 'about', 'it', 'this', 'that',
        'help', 'hello', 'hi', 'eride', 'e-ride', 'bamenda', 'today', 'now', 'place', 'places', 'someone', 'somebody',
        // fr
        'je', 'j', 'moi', 'mon', 'ma', 'mes', 'un', 'une', 'des', 'le', 'la', 'les', 'l', 'de', 'du', 'd', 'en', 'au', 'aux', 'et', 'ou', 'où',
        'cherche', 'chercher', 'recherche', 'veux', 'voudrais', 'besoin', 'acheter', 'trouver', 'trouve', 'est', 'il', 'y', 'a', 'qui', 'quoi',
        'quel', 'quelle', 'combien', 'prix', 'près', 'pres', 'ici', 'svp', 'stp', 'bon', 'bonne', 'pas', 'cher', 'avec', 'pour', 'sur', 'dans',
        'vend', 'vendre', 'vends', 'avez', 'vous', 'as', 'tu', 'peux', 'peut', 'aller', 'bonjour', 'salut', 'quelqu', 'endroit',
        // pidgin
        'dey', 'wey', 'fit', 'fine', 'ma', 'wan', 'abeg', 'which', 'side', 'na', 'sabi', 'go', 'don', 'make', 'how', 'thing',
    ];

    /** Words that mean an Explore category, so "pharmacy" finds the category even without a checked place. */
    private const CATEGORY_WORDS = [
        'pharmacy' => ['pharmacy', 'pharmacie', 'chemist', 'drug', 'drugs', 'medicine', 'medicament', 'médicament'],
        'health' => ['hospital', 'hôpital', 'hopital', 'clinic', 'clinique', 'doctor', 'docteur', 'médecin', 'medecin', 'lab', 'laboratory'],
        'bank' => ['bank', 'banque', 'atm', 'cash', 'money', 'argent', 'distributeur', 'microfinance'],
        'stay' => ['hotel', 'hôtel', 'hotels', 'room', 'chambre', 'lodge', 'guest', 'motel', 'auberge', 'sleep', 'dormir'],
        'bus' => ['bus', 'agence', 'agency', 'travel', 'voyage'],
        'eat' => ['eat', 'manger', 'restaurant', 'food', 'cafe', 'café', 'bakery', 'boulangerie', 'chop', 'snack'],
        'swim' => ['swim', 'swimming', 'pool', 'piscine', 'nager', 'nage'],
        'party' => ['party', 'bar', 'club', 'nightclub', 'boîte', 'boite', 'lounge', 'drink', 'beer', 'bière', 'sortir', 'night', 'nuit', 'dance', 'danser'],
        'visit' => ['visit', 'visiter', 'tourist', 'touriste', 'museum', 'musée', 'musee', 'waterfall', 'falls', 'chute', 'park', 'parc', 'sight', 'see', 'voir', 'view'],
        'market' => ['market', 'marché', 'marche', 'mall', 'shopping'],
        'homes' => ['rent', 'louer', 'location', 'studio', 'apartment', 'appartement', 'house', 'maison', 'flat', 'shop to rent'],
    ];

    /** French and English words for the same thing, so either finds a listing written in the other. */
    private const SAME = [
        'téléphone' => 'phone', 'telephone' => 'phone', 'portable' => 'phone', 'chaussure' => 'shoe', 'ordinateur' => 'laptop',
        'voiture' => 'car', 'moto' => 'motorbike', 'maison' => 'house', 'chambre' => 'room', 'vêtement' => 'clothe', 'vetement' => 'clothe',
        'robe' => 'dress', 'sac' => 'bag', 'montre' => 'watch', 'lit' => 'bed', 'matelas' => 'mattress', 'frigo' => 'fridge',
        'réfrigérateur' => 'fridge', 'télé' => 'tv', 'tele' => 'tv', 'télévision' => 'tv', 'riz' => 'rice', 'poulet' => 'chicken',
        'poisson' => 'fish', 'gâteau' => 'cake', 'gateau' => 'cake', 'coiffure' => 'hair', 'coiffeur' => 'hair', 'tresse' => 'braid',
        'couture' => 'tailor', 'tailleur' => 'tailor', 'plombier' => 'plumber', 'électricien' => 'electrician', 'electricien' => 'electrician',
        'réparation' => 'repair', 'reparation' => 'repair', 'réparer' => 'repair', 'reparer' => 'repair', 'nettoyage' => 'cleaning',
        'cours' => 'lesson', 'fix' => 'repair', 'photographe' => 'photo', 'emploi' => 'job', 'travail' => 'job', 'chauffeur' => 'driver', 'vendeur' => 'sales',
    ];

    /** The words of a question that say what the person wants. */
    public static function terms(string $question): array
    {
        $q = mb_strtolower(strip_tags($question));
        $q = preg_replace("/[’'`]/u", ' ', $q);
        $words = preg_split('/[^\p{L}\p{N}]+/u', $q, -1, PREG_SPLIT_NO_EMPTY);
        $stop = array_flip(self::STOP);
        $out = [];
        foreach ($words as $w) {
            if (mb_strlen($w) < 2 || isset($stop[$w])) {
                continue;
            }
            // Plural "s" off for the match ("shoes" finds "shoe"), but keep short words like "ps4" as they are.
            $out[] = mb_strlen($w) > 4 && str_ends_with($w, 's') ? mb_substr($w, 0, -1) : $w;
        }
        $out = array_slice(array_values(array_unique($out)), 0, 5);
        foreach ($out as $w) {
            $other = self::SAME[$w] ?? array_search($w, self::SAME, true);
            if (is_string($other) && !in_array($other, $out, true)) {
                $out[] = $other;
            }
        }
        return array_slice($out, 0, 7);
    }

    /**
     * Results, best first: each {ref, type, id, title, subtitle, price, photo, source}.
     *
     * @param string[] $services the services switched on in the app
     */
    public static function search(string $question, ?float $lat, ?float $lng, array $services, string $lang = 'en'): array
    {
        $terms = self::terms($question);
        if (!$terms) {
            return ['terms' => [], 'results' => [], 'categories' => []];
        }
        $has = fn(string $s) => in_array($s, $services, true);
        $results = [];
        $sources = [
            'market' => fn() => $has('market') ? self::market($terms) : [],
            'food' => fn() => $has('food') ? self::food($terms) : [],
            'places' => fn() => ($has('explore') || $has('hotels')) ? self::places($terms, $lat, $lng) : [],
            'tours' => fn() => $has('explore') ? self::tours($terms) : [],
            'jobs' => fn() => $has('jobs') ? self::jobs($terms) : [],
            'homes' => fn() => $has('explore') ? self::homes($terms) : [],
            'bus' => fn() => $has('bus') ? self::bus($terms) : [],
        ];
        foreach ($sources as $name => $run) {
            try {
                array_push($results, ...$run());
            } catch (Throwable $e) {
                // One source failing never hides the others.
                report($e);
            }
        }
        // Only real word matches, best first: words found in the title count most.
        $results = array_values(array_filter($results, fn($r) => $r['score'] > 0));
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
        $results = array_slice($results, 0, self::MAX_RESULTS);
        foreach ($results as $i => &$r) {
            $r['ref'] = 'r' . ($i + 1);
            unset($r['score']);
        }
        unset($r);
        return ['terms' => $terms, 'results' => $results, 'categories' => self::categories($terms, $services, $lang)];
    }

    /**
     * How well a text matches: 3 per word found in the title, 1 per word found
     * elsewhere, as the start of a word ("bus" is not in "busy"). 0 means the
     * database's rough match was wrong and the result is dropped.
     */
    private static function score(array $terms, string $title, string $rest = ''): int
    {
        $t = mb_strtolower($title);
        $r = mb_strtolower($rest);
        $s = 0;
        foreach ($terms as $w) {
            // Short words must be whole words (or their plural): "bus" is not "busy". Longer ones may start a word.
            $re = mb_strlen($w) <= 4
                ? '/(^|[^\p{L}\p{N}])' . preg_quote($w, '/') . '(s|es)?($|[^\p{L}\p{N}])/u'
                : '/(^|[^\p{L}\p{N}])' . preg_quote($w, '/') . '/u';
            if (preg_match($re, $t)) {
                $s += 3;
            } elseif ($r !== '' && preg_match($re, $r)) {
                $s += 1;
            }
        }
        return $s;
    }

    /** A source's head start, only for a real match. */
    private static function bonus(int $score, int $extra): int
    {
        return $score > 0 ? $score + $extra : 0;
    }

    /** Any of the words in any of the columns. */
    private static function like($query, array $columns, array $terms)
    {
        return $query->where(function ($q) use ($columns, $terms) {
            foreach ($terms as $w) {
                foreach ($columns as $c) {
                    $q->orWhere($c, 'like', '%' . str_replace(['%', '_'], ['\%', '\_'], $w) . '%');
                }
            }
        });
    }

    private static function market(array $terms): array
    {
        if (!class_exists(\Modules\MarketManagement\Entities\MarketItem::class)) {
            return [];
        }
        $out = [];
        $items = self::like(\Modules\MarketManagement\Entities\MarketItem::with(['shop:id,name,slug,area_label', 'photos'])->where('status', 'live')->listed()
            ->whereHas('shop', fn($s) => $s->where('status', '!=', 'closed')), ['title', 'description', 'tags_search'], $terms)
            ->latest()->limit(20)->get();
        foreach ($items as $it) {
            $service = $it->condition === 'service';
            $out[] = [
                'type' => 'market_item',
                'id' => $it->code,
                'title' => Str::limit($it->title, 60),
                'subtitle' => trim(($service ? 'Service · ' : '') . ($it->shop?->name ?? '') . ($it->shop?->area_label ? ' · ' . $it->shop->area_label : '')),
                'price' => $it->price !== null ? (float)$it->price : null,
                'photo' => $it->cover()?->url(),
                'source' => $service ? 'market_service' : 'market',
                'score' => self::bonus(self::score($terms, $it->title . ' ' . implode(' ', $it->tags ?? []), (string)$it->description), 1),
            ];
        }
        usort($out, fn($a, $b) => $b['score'] <=> $a['score']);
        $out = array_slice(array_values(array_filter($out, fn($r) => $r['score'] > 0)), 0, self::PER_SOURCE);
        // Asked for and not on the Market: sellers see it when posting.
        if (class_exists(\Modules\MarketManagement\Lib\MarketTags::class)) {
            \Modules\MarketManagement\Lib\MarketTags::record(implode(' ', array_slice($terms, 0, 3)), (bool)$out);
        }
        $shops = self::like(\Modules\MarketManagement\Entities\MarketShop::where('status', '!=', 'closed'), ['name', 'about'], $terms)->limit(3)->get();
        foreach ($shops as $sh) {
            $out[] = [
                'type' => 'market_shop', 'id' => $sh->slug, 'title' => $sh->name, 'subtitle' => trim('Market shop' . ($sh->area_label ? ' · ' . $sh->area_label : '')),
                'price' => null, 'photo' => null, 'source' => 'market', 'score' => self::score($terms, $sh->name, (string)$sh->about),
            ];
        }
        return $out;
    }

    private static function food(array $terms): array
    {
        if (!class_exists(\Modules\StoreManagement\Entities\Store::class)) {
            return [];
        }
        $out = [];
        // Brands (the main store; branches belong to it).
        $stores = self::like(\Modules\StoreManagement\Entities\Store::where('is_active', 1)->whereNull('parent_id'), ['name', 'description', 'type'], $terms)->limit(3)->get();
        foreach ($stores as $st) {
            $out[] = [
                'type' => 'store', 'id' => $st->id, 'title' => $st->name, 'subtitle' => 'Food & Shops',
                'price' => null, 'photo' => $st->logoUrl(), 'source' => 'food', 'score' => self::bonus(self::score($terms, $st->name, (string)$st->description), 1),
            ];
        }
        // Dishes and products: the store that has them.
        $items = self::like(\Modules\StoreManagement\Entities\StoreItem::with('store:id,name,parent_id,is_active,logo')->where('is_available', 1), ['name', 'description'], $terms)
            ->limit(10)->get();
        $seen = [];
        foreach ($items as $it) {
            $store = $it->store;
            if (!$store || !$store->is_active) {
                continue;
            }
            $brandId = $store->parent_id ?? $store->id;
            if (isset($seen[$brandId . $it->name])) {
                continue;
            }
            $seen[$brandId . $it->name] = true;
            $out[] = [
                'type' => 'store', 'id' => $brandId, 'title' => Str::limit($it->name, 60), 'subtitle' => $store->displayName(),
                'price' => (float)$it->price, 'photo' => $it->image ? dynamicStorage('storage/app/public/store/item') . '/' . $it->image : $store->logoUrl(),
                'source' => 'food', 'score' => self::score($terms, $it->name, (string)$it->description),
            ];
        }
        usort($out, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($out, 0, self::PER_SOURCE);
    }

    private static function places(array $terms, ?float $lat, ?float $lng): array
    {
        $out = [];
        $places = self::like(\Modules\PlaceManagement\Entities\Place::where('is_active', 1), ['name', 'description', 'category', 'story'], $terms)->limit(8)->get();
        foreach ($places as $p) {
            $card = \Modules\PlaceManagement\Transformers\PlacePresenter::verified($p, $lat, $lng);
            $out[] = [
                'type' => 'place', 'id' => $p->id,
                'title' => $p->name,
                'subtitle' => trim(str_replace('_', ' ', ucfirst((string)$p->category)) . ($card['distance_km'] !== null ? ' · ' . $card['distance_km'] . ' km' : '')),
                'price' => null, 'photo' => $card['photos'][0]['thumb'] ?? null, 'source' => 'place',
                'score' => self::bonus(self::score($terms, $p->name, str_replace('_', ' ', (string)$p->category) . ' ' . $p->description . ' ' . $p->story), 1),
                // The app opens the place page from this card without another request.
                'place' => $card,
            ];
        }
        usort($out, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($out, 0, self::PER_SOURCE);
    }

    private static function tours(array $terms): array
    {
        $out = [];
        $tours = self::like(\Modules\PlaceManagement\Entities\Tour::where('status', 'published'), ['title', 'title_fr', 'intro'], $terms)->limit(2)->get();
        foreach ($tours as $t) {
            $out[] = ['type' => 'tour', 'id' => $t->id, 'title' => $t->title, 'subtitle' => 'Tour', 'price' => null, 'photo' => null,
                'source' => 'tour', 'score' => self::score($terms, $t->title, (string)$t->intro)];
        }
        return $out;
    }

    private static function jobs(array $terms): array
    {
        if (!class_exists(\Modules\MarketManagement\Entities\JobPost::class)) {
            return [];
        }
        $out = [];
        $jobs = self::like(\Modules\MarketManagement\Entities\JobPost::with('employer:id,name')->where('status', 'live'), ['title', 'description', 'category'], $terms)
            ->latest('live_at')->limit(6)->get();
        foreach ($jobs as $j) {
            $out[] = ['type' => 'job', 'id' => $j->code, 'title' => Str::limit($j->title, 60),
                'subtitle' => trim('Job · ' . ($j->employer?->name ?? '') . ($j->area_label ? ' · ' . $j->area_label : '')),
                'price' => $j->pay_min !== null ? (float)$j->pay_min : null, 'photo' => null, 'source' => 'job',
                'score' => self::score($terms, $j->title, $j->category . ' ' . $j->description)];
        }
        usort($out, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($out, 0, 3);
    }

    private static function homes(array $terms): array
    {
        if (!class_exists(\Modules\PlaceManagement\Entities\Listing::class)) {
            return [];
        }
        $out = [];
        $homes = self::like(\Modules\PlaceManagement\Entities\Listing::where('status', 'live'), ['title', 'type', 'neighbourhood', 'description'], $terms)->latest()->limit(6)->get();
        foreach ($homes as $l) {
            $out[] = ['type' => 'listing', 'id' => $l->id, 'title' => Str::limit($l->title, 60),
                'subtitle' => trim('To rent · ' . str_replace('_', ' ', (string)$l->type) . ($l->neighbourhood ? ' · ' . $l->neighbourhood : '')),
                'price' => $l->rent !== null ? (float)$l->rent : null, 'photo' => null, 'source' => 'home',
                'score' => self::score($terms, $l->title, $l->type . ' ' . $l->neighbourhood . ' ' . $l->description)];
        }
        usort($out, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($out, 0, 3);
    }

    private static function bus(array $terms): array
    {
        if (!class_exists(\Modules\BusManagement\Entities\BusDestination::class)) {
            return [];
        }
        $out = [];
        $cities = \Modules\BusManagement\Entities\BusDestination::where('active', 1)
            ->where(fn($q) => self::like($q, ['city'], $terms))->distinct()->limit(3)->pluck('city');
        foreach ($cities as $city) {
            $out[] = ['type' => 'bus', 'id' => $city, 'title' => 'Bus to ' . $city, 'subtitle' => 'Bus tickets', 'price' => null, 'photo' => null,
                'source' => 'bus', 'score' => self::bonus(self::score($terms, $city), 2)];
        }
        return $out;
    }

    /** Explore categories the words name ("pharmacy", "swim", "rent"), active ones only. */
    private static function categories(array $terms, array $services, string $lang): array
    {
        if (!in_array('explore', $services, true) && !in_array('hotels', $services, true)) {
            return [];
        }
        $found = [];
        foreach (self::CATEGORY_WORDS as $key => $words) {
            foreach ($terms as $t) {
                foreach ($words as $w) {
                    if ($t === $w || (mb_strlen($t) >= 4 && str_starts_with($w, $t))) {
                        $found[$key] = true;
                    }
                }
            }
        }
        if (!$found) {
            return [];
        }
        return \Modules\PlaceManagement\Entities\PlaceCategory::whereIn('key', array_keys($found))->where('is_active', 1)->get()
            ->map(fn($c) => ['key' => $c->key, 'name' => $c->localName($lang)])->values()->all();
    }
}
