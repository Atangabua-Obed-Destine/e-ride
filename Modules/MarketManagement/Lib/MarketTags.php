<?php

namespace Modules\MarketManagement\Lib;

use Illuminate\Support\Facades\DB;
use Modules\MarketManagement\Entities\MarketItem;
use Throwable;

/**
 * Market tags: the other words buyers use for what is posted, so a "Sony
 * console" is found by "ps4", "playstation" or "jeux vidéo".
 *
 * Suggestions cost nothing: a word list (English, French, Pidgin), the
 * category's words, and tags other sellers already use for similar posts.
 * Searches are counted per word and day, with those that found nothing, so
 * sellers see what people are looking for.
 */
class MarketTags
{
    public const MAX_TAGS = 8;
    public const MIN_GOOD = 3;
    private const MAX_LEN = 24;

    /** Words that mean the same thing. A word from the title brings the rest of its group. */
    private const GROUPS = [
        ['phone', 'smartphone', 'téléphone', 'portable', 'android', 'iphone', 'mobile'],
        ['iphone', 'apple', 'ios'],
        ['samsung', 'galaxy', 'android'],
        ['tecno', 'infinix', 'itel', 'android', 'phone'],
        ['laptop', 'ordinateur', 'pc', 'computer', 'notebook', 'macbook'],
        ['ps4', 'ps5', 'playstation', 'console', 'jeux vidéo', 'video game', 'game', 'manette'],
        ['xbox', 'console', 'jeux vidéo', 'video game'],
        ['tv', 'télé', 'television', 'téléviseur', 'smart tv', 'écran'],
        ['fridge', 'frigo', 'réfrigérateur', 'freezer', 'congélateur'],
        ['generator', 'groupe électrogène', 'générateur', 'solar', 'backup power'],
        ['solar', 'panneau solaire', 'solar panel', 'battery', 'batterie', 'inverter', 'onduleur'],
        ['speaker', 'baffle', 'enceinte', 'sound', 'bluetooth'],
        ['headphones', 'écouteurs', 'earphones', 'airpods', 'casque'],
        ['charger', 'chargeur', 'cable', 'câble', 'power bank'],
        ['shoe', 'shoes', 'chaussure', 'chaussures', 'sneakers', 'baskets', 'heels', 'sandals', 'sandales'],
        ['dress', 'robe', 'gown', 'kaba', 'kabba'],
        ['shirt', 'chemise', 't-shirt', 'top', 'polo'],
        ['trousers', 'pantalon', 'jeans', 'jean', 'trouser'],
        ['bag', 'sac', 'handbag', 'sac à main', 'backpack'],
        ['watch', 'montre', 'smartwatch'],
        ['wig', 'perruque', 'hair', 'mèches', 'weave', 'attachment'],
        ['braids', 'tresses', 'hair', 'coiffure', 'hairdresser', 'salon'],
        ['makeup', 'maquillage', 'beauty', 'cosmetics', 'cosmétiques'],
        ['perfume', 'parfum', 'fragrance', 'cologne'],
        ['cream', 'crème', 'lotion', 'skin care', 'soin'],
        ['bed', 'lit', 'mattress', 'matelas', 'foam'],
        ['chair', 'chaise', 'sofa', 'couch', 'fauteuil', 'canapé', 'furniture', 'meuble'],
        ['table', 'desk', 'bureau', 'furniture', 'meuble'],
        ['lamp', 'lampe', 'light', 'lumière', 'bulb', 'ampoule'],
        ['pot', 'marmite', 'casserole', 'cooking', 'cuisine', 'kitchen'],
        ['gas', 'gaz', 'cooker', 'cuisinière', 'gas cylinder', 'bouteille de gaz'],
        ['baby', 'bébé', 'kids', 'enfant', 'children', 'toddler'],
        ['toy', 'jouet', 'toys', 'jouets'],
        ['rice', 'riz', 'food', 'foodstuff', 'vivres'],
        ['cake', 'gâteau', 'birthday cake', 'pastry', 'pâtisserie'],
        ['car', 'voiture', 'vehicle', 'véhicule', 'auto'],
        ['motorbike', 'moto', 'bike', 'okada'],
        ['tyre', 'tire', 'pneu', 'wheel', 'roue'],
        ['spare parts', 'pièces détachées', 'parts', 'pièces', 'engine', 'moteur'],
        ['repair', 'réparation', 'fix', 'technician', 'technicien', 'dépannage'],
        ['plumber', 'plombier', 'plumbing', 'plomberie', 'water'],
        ['electrician', 'électricien', 'wiring', 'électricité'],
        ['tailor', 'tailleur', 'couture', 'sewing', 'seamstress', 'couturière'],
        ['cleaning', 'nettoyage', 'cleaner', 'ménage', 'laundry', 'lessive'],
        ['lessons', 'cours', 'tutor', 'répétiteur', 'teacher', 'classes', 'math'],
        ['photographer', 'photographe', 'photo', 'photography', 'video', 'vidéo', 'shooting'],
        ['decoration', 'décoration', 'events', 'événement', 'party', 'wedding', 'mariage'],
        ['catering', 'traiteur', 'cook', 'chef', 'food'],
        ['ebook', 'livre', 'book', 'pdf', 'guide'],
        ['course', 'formation', 'training', 'tutorial', 'online course'],
        ['music', 'musique', 'beat', 'song', 'mp3'],
        ['design', 'logo', 'flyer', 'graphic design', 'graphisme'],
        ['template', 'modèle', 'cv', 'resume'],
    ];

    /** Words for each category and kind of service. */
    private const CATEGORY_TAGS = [
        'fashion' => ['fashion', 'mode', 'clothes', 'vêtements'],
        'phones' => ['phone', 'téléphone', 'smartphone'],
        'electronics' => ['electronics', 'électronique'],
        'home' => ['home', 'maison', 'house'],
        'food' => ['food', 'nourriture'],
        'beauty' => ['beauty', 'beauté'],
        'kids' => ['kids', 'enfants', 'baby'],
        'vehicle_parts' => ['car parts', 'pièces auto', 'spare parts'],
        'digital' => ['digital', 'download', 'téléchargement'],
        'hair_beauty' => ['hair', 'coiffure', 'beauty', 'salon'],
        'repairs' => ['repair', 'réparation', 'technician'],
        'tailoring' => ['tailor', 'couture', 'sewing'],
        'cleaning' => ['cleaning', 'nettoyage', 'ménage'],
        'lessons' => ['lessons', 'cours', 'tutor'],
        'events' => ['events', 'événement', 'party'],
        'photography' => ['photographer', 'photographe', 'photo'],
    ];

    private const STOP = ['a', 'an', 'the', 'and', 'or', 'for', 'with', 'of', 'in', 'on', 'to', 'new', 'used', 'good', 'nice', 'very', 'clean',
        'le', 'la', 'les', 'un', 'une', 'des', 'de', 'du', 'et', 'ou', 'pour', 'avec', 'neuf', 'neuve', 'occasion', 'bon', 'bonne', 'très',
        'test', 'private', 'sale', 'vente', 'buy', 'sell', 'selling', 'cheap', 'pas', 'cher', 'original', 'quality', 'qualité', 'size', 'taille'];

    /** One tag: lower case, no "#", single spaces, letters, digits, spaces, "-" and "+" only. Null when not usable. */
    public static function clean(string $tag): ?string
    {
        $t = mb_strtolower(trim($tag));
        $t = ltrim($t, '#');
        $t = preg_replace('/[^\p{L}\p{N}\s\-+]/u', ' ', $t);
        $t = trim(preg_replace('/\s+/u', ' ', $t));
        $len = mb_strlen($t);
        return $len >= 2 && $len <= self::MAX_LEN ? $t : null;
    }

    /** A list of tags from the app (array, or "a, b, c"): cleaned, unique, at most MAX_TAGS. */
    public static function normalize(mixed $tags): array
    {
        if (is_string($tags)) {
            $tags = preg_split('/[,;\n]+/u', $tags);
        }
        $out = [];
        foreach ((array)$tags as $t) {
            if (is_scalar($t) && ($c = self::clean((string)$t)) !== null && !in_array($c, $out, true)) {
                $out[] = $c;
            }
            if (count($out) === self::MAX_TAGS) {
                break;
            }
        }
        return $out;
    }

    /** "|ps4|playstation|" for the search column, or null. */
    public static function searchText(array $tags): ?string
    {
        return $tags ? mb_substr('|' . implode('|', $tags) . '|', 0, 500) : null;
    }

    /** The words of a title worth suggesting from. */
    public static function words(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}+\-]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
        $stop = array_flip(self::STOP);
        return array_values(array_unique(array_filter($words, fn($w) => mb_strlen($w) >= 2 && !isset($stop[$w]) && !preg_match('/^\d+$/', $w))));
    }

    /**
     * Tags to offer while posting: other names for the title's words, the
     * category's words, then tags other sellers use for similar posts. Without
     * the ones already chosen.
     */
    public static function suggest(string $title, ?string $category, ?string $serviceKind, array $chosen = []): array
    {
        $words = self::words($title);
        $chosen = self::normalize($chosen);
        $titleLower = mb_strtolower($title);
        $out = [];
        $add = function (string $tag) use (&$out, $chosen, $titleLower) {
            $c = self::clean($tag);
            // Words already in the title add little: the title is searched anyway.
            if ($c !== null && !in_array($c, $out, true) && !in_array($c, $chosen, true) && !preg_match('/(^|\W)' . preg_quote($c, '/') . '($|\W)/u', $titleLower)) {
                $out[] = $c;
            }
        };
        foreach (self::GROUPS as $group) {
            foreach ($words as $w) {
                $hit = false;
                foreach ($group as $g) {
                    if ($w === $g || (mb_strlen($w) >= 4 && (str_starts_with($g, $w) || str_starts_with($w, $g)))) {
                        $hit = true;
                        break;
                    }
                }
                if ($hit) {
                    foreach ($group as $g) {
                        $add($g);
                    }
                    break;
                }
            }
        }
        foreach ([$serviceKind, $category] as $key) {
            foreach (self::CATEGORY_TAGS[$key] ?? [] as $t) {
                $add($t);
            }
        }
        // What other sellers tag similar posts with.
        if ($words) {
            try {
                $counts = [];
                MarketItem::whereNotNull('tags')->where('status', MarketItem::LIVE)
                    ->where(function ($q) use ($words) {
                        foreach (array_slice($words, 0, 4) as $w) {
                            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $w) . '%';
                            $q->orWhere('title', 'like', $like)->orWhere('tags_search', 'like', '%|' . str_replace(['%', '_'], ['\%', '\_'], $w) . '%');
                        }
                    })->latest()->limit(60)->pluck('tags')
                    ->each(function ($tags) use (&$counts) {
                        foreach ((array)$tags as $t) {
                            $counts[$t] = ($counts[$t] ?? 0) + 1;
                        }
                    });
                arsort($counts);
                foreach (array_keys(array_slice($counts, 0, 6, true)) as $t) {
                    $add((string)$t);
                }
            } catch (Throwable $e) {
                report($e);
            }
        }
        return array_slice($out, 0, 12);
    }

    /** Count a search: the words someone looked for, and whether it found anything. */
    public static function record(string $query, bool $found): void
    {
        $term = mb_substr(trim(preg_replace('/\s+/u', ' ', mb_strtolower(strip_tags($query)))), 0, 60);
        if (mb_strlen($term) < 2) {
            return;
        }
        try {
            $day = today()->toDateString();
            DB::table('market_search_terms')->insertOrIgnore(['term' => $term, 'day' => $day, 'searches' => 0, 'misses' => 0, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('market_search_terms')->where('term', $term)->where('day', $day)->update([
                'searches' => DB::raw('searches + 1'),
                'misses' => DB::raw('misses + ' . ($found ? 0 : 1)),
                'updated_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * What people looked for in the last 7 days and did not find: the ones
     * about this post's words first, then the most wanted overall.
     *
     * @return array{related: array<int, array{term: string, count: int}>, top: array<int, array{term: string, count: int}>}
     */
    public static function wanted(array $words): array
    {
        $since = today()->subDays(6)->toDateString();
        $base = fn() => DB::table('market_search_terms')->where('day', '>=', $since)->where('misses', '>', 0)
            ->groupBy('term')->select('term', DB::raw('SUM(misses) as n'))->orderByDesc('n');
        $related = [];
        if ($words) {
            $related = $base()->where(function ($q) use ($words) {
                foreach (array_slice($words, 0, 8) as $w) {
                    $q->orWhere('term', 'like', '%' . str_replace(['%', '_'], ['\%', '\_'], $w) . '%');
                }
            })->limit(3)->get()->map(fn($r) => ['term' => $r->term, 'count' => (int)$r->n])->all();
        }
        $top = $base()->limit(5)->get()->map(fn($r) => ['term' => $r->term, 'count' => (int)$r->n])->all();
        return ['related' => $related, 'top' => $top];
    }
}
