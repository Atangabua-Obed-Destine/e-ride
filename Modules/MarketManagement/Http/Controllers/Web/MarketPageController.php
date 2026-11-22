<?php

namespace Modules\MarketManagement\Http\Controllers\Web;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\MarketManagement\Entities\MarketItem;
use Modules\MarketManagement\Entities\MarketShop;
use Modules\MarketManagement\Lib\MarketLinks;
use Modules\MarketManagement\Lib\MarketSettings;
use Modules\MarketManagement\Service\ShopService;

/**
 * The pages a WhatsApp link opens: a shop, an item, and "get the app". Light, server-made,
 * English or French from the browser, with link-preview tags. No phone numbers: buyers chat
 * with the seller in the E-Ride app.
 */
class MarketPageController extends Controller
{
    public function __construct(protected ShopService $shops)
    {
    }

    public function shop(Request $request, string $slug): Response
    {
        $shop = MarketShop::where('slug', $slug)->first();
        $t = $this->words($request);
        if (!$shop || !$shop->isLive() || !MarketSettings::enabled()) {
            return $this->unavailable($t, $t['shop_gone'], null, 404);
        }
        $this->shops->visit($shop, $request->ip() . '|' . $request->userAgent());
        $base = MarketItem::with('photos')->where('shop_id', $shop->id)->whereIn('status', MarketItem::PUBLIC)->where('zama_only', false);
        $total = (clone $base)->count();
        // The shop's categories, with how many items each (only those it has).
        $categories = (clone $base)->toBase()->whereNotNull('category')->selectRaw('category, COUNT(*) AS n')->groupBy('category')
            ->orderByDesc('n')->pluck('n', 'category')->all();
        $categories = array_intersect_key($categories, array_flip(MarketSettings::CATEGORIES));
        // Search in this shop: title, description and tags (the page also filters as you type).
        $q = trim(mb_substr((string)$request->query('q', ''), 0, 80));
        if ($q !== '') {
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
            $base->where(fn($w) => $w->where('title', 'like', $like)->orWhere('description', 'like', $like)->orWhere('tags_search', 'like', $like));
        }
        $cat = isset($categories[$request->query('cat')]) ? (string)$request->query('cat') : null;
        if ($cat) {
            $base->where('category', $cat);
        }
        $inStock = $request->boolean('stock');
        if ($inStock) {
            $base->where('status', MarketItem::LIVE);
        }
        // Newest, or by price (items without a price, "Ask the seller", go last).
        $sort = in_array($request->query('sort'), ['low', 'high'], true) ? (string)$request->query('sort') : 'new';
        if ($sort === 'new') {
            $base->latest();
        } else {
            $base->orderByRaw('price IS NULL')->orderBy('price', $sort === 'low' ? 'asc' : 'desc')->latest();
        }
        $items = $base->limit(60)->get();
        $filtered = $q !== '' || $cat || $inStock;
        $cover = MarketItem::with('photos')->where('shop_id', $shop->id)->whereIn('status', MarketItem::PUBLIC)->where('zama_only', false)->latest()->first()?->cover();
        $shop->loadMissing('venue');
        return response()->view('marketmanagement::web.shop', [
            't' => $t,
            'shop' => $shop,
            'items' => $items,
            'total' => $total,
            'q' => $q,
            'categories' => $categories,
            'cat' => $cat,
            'sort' => $sort,
            'inStock' => $inStock,
            'filtered' => $filtered,
            'url' => MarketLinks::shop($shop),
            // The link picture: the shopfront, else the newest item, else the logo.
            'ogImage' => $shop->shopfrontUrl() ?? $cover?->ogUrl() ?? $shop->logoUrl(),
            'openApp' => MarketLinks::openInApp('/s/' . $shop->slug, route('market.shop-app', ['slug' => $shop->slug])),
        ]);
    }

    /** No app yet, from a shop's page: what E-Ride is, and the Play Store link that opens this shop after installing. */
    public function shopApp(Request $request, string $slug): Response
    {
        $shop = MarketShop::with('venue')->where('slug', $slug)->first();
        $t = $this->words($request);
        if (!$shop || !$shop->isLive()) {
            return $this->unavailable($t, $t['shop_gone'], null, 404);
        }
        return response()->view('marketmanagement::web.shop-app', [
            't' => $t,
            'shop' => $shop,
            'shopUrl' => MarketLinks::shop($shop),
            'playUrl' => MarketLinks::playStore('s:' . $shop->slug),
            'openApp' => MarketLinks::openInApp('/s/' . $shop->slug, MarketLinks::playStore('s:' . $shop->slug)),
        ])->header('X-Robots-Tag', 'noindex');
    }

    public function item(Request $request, string $code): Response
    {
        $item = MarketItem::with(['photos', 'shop', 'auction'])->where('code', $code)->first();
        $t = $this->words($request);
        if (!$item || !$item->isPublic() || !MarketSettings::enabled()) {
            $shop = $item?->shop?->isLive() ? $item->shop : null;
            return $this->unavailable($t, $t['item_gone'], $shop, 404);
        }
        $this->shops->visit($item, $request->ip() . '|' . $request->userAgent());
        $settings = MarketSettings::all();
        return response()->view('marketmanagement::web.item', [
            't' => $t,
            'item' => $item,
            'shop' => $item->shop,
            'url' => MarketLinks::item($item),
            'shopUrl' => MarketLinks::shop($item->shop),
            'cover' => $item->cover(),
            'strongWarning' => $item->isDigital() && $settings['digital_warning'] && !$item->shop->isVerified(),
            'auction' => $item->isAuction() ? $item->auction : null,
            'getAppUrl' => route('market.get-app', ['code' => $item->code]),
            'openApp' => MarketLinks::openInApp('/i/' . $item->code, route('market.get-app', ['code' => $item->code])),
        ]);
    }

    /** No app yet: what E-Ride is, and the Play Store link that opens this item after installing. */
    public function getApp(Request $request, string $code): Response
    {
        $item = MarketItem::with(['photos', 'shop'])->where('code', $code)->first();
        $t = $this->words($request);
        if (!$item || !$item->isPublic()) {
            return $this->unavailable($t, $t['item_gone'], null, 404);
        }
        return response()->view('marketmanagement::web.get-app', [
            't' => $t,
            'item' => $item,
            'shop' => $item->shop,
            'cover' => $item->cover(),
            'itemUrl' => MarketLinks::item($item),
            'playUrl' => MarketLinks::playStore('i:' . $item->code),
            'openApp' => MarketLinks::openInApp('/i/' . $item->code, MarketLinks::playStore('i:' . $item->code)),
        ])->header('X-Robots-Tag', 'noindex');
    }

    /** Lets Android open these links straight in the app once the domain and signing key are set. */
    public function assetLinks(): JsonResponse
    {
        $prints = array_values(array_filter(array_map('trim', explode(',', (string)config('marketmanagement.android_sha256')))));
        $statements = $prints ? [[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => ['namespace' => 'android_app', 'package_name' => config('marketmanagement.android_package'), 'sha256_cert_fingerprints' => $prints],
        ]] : [];
        return response()->json($statements);
    }

    protected function unavailable(array $t, string $message, ?MarketShop $shop, int $status): Response
    {
        return response()->view('marketmanagement::web.unavailable', [
            't' => $t, 'message' => $message, 'shop' => $shop, 'shopUrl' => $shop ? MarketLinks::shop($shop) : null,
        ], $status)->header('X-Robots-Tag', 'noindex');
    }

    /** The page's words, English or French from the browser. */
    protected function words(Request $request): array
    {
        $fr = str_starts_with(strtolower((string)$request->getPreferredLanguage(['en', 'fr'])), 'fr');
        $en = [
            'lang' => 'en',
            'open_app' => 'Open in the app',
            'chat' => 'Chat with the seller in E-Ride',
            'no_phone' => 'No phone numbers here: chat happens in the app',
            'report' => 'Report',
            'scam_notice' => 'Sellers on E-Ride Market are independent. E-Ride does not hold your money: see the item before you pay.',
            'stay_safe' => 'Stay safe: meet in a public place or use E-Ride delivery, check the item before you pay, and never send money to "reserve" an item.',
            'digital_warning_title' => 'Digital products: be extra careful',
            'digital_warning' => 'This shop is new and not yet verified. Look at the preview first. You pay the seller directly and E-Ride cannot get your money back. Never pay for exam leaks, "investment" plans or job offers: they are scams.',
            'items' => ':n items',
            'one_item' => '1 item',
            'no_items' => 'No items yet.',
            'search_shop' => 'Search in this shop',
            'search' => 'Search',
            'no_match' => 'Nothing here matches “:q”.',
            'found' => ':n found',
            'show_all' => 'Show all items',
            'all' => 'All',
            'sort_new' => 'Newest',
            'sort_low' => 'Lowest price',
            'sort_high' => 'Highest price',
            'in_stock' => 'Hide sold out',
            'no_filter_match' => 'Nothing here matches these choices.',
            'cat_fashion' => 'Fashion', 'cat_phones' => 'Phones', 'cat_electronics' => 'Electronics', 'cat_home' => 'Home', 'cat_food' => 'Food',
            'cat_beauty' => 'Beauty', 'cat_kids' => 'Kids', 'cat_vehicle_parts' => 'Vehicle parts', 'cat_services' => 'Services',
            'cat_digital' => 'Digital', 'cat_other' => 'Other',
            'negotiable' => 'Price can be discussed',
            'ask_price' => 'Ask the seller',
            'new' => 'New',
            'used' => 'Used',
            'digital' => 'Digital product',
            'left' => ':n left',
            'sold_out' => 'Sold out',
            'pickup' => 'Pickup in :area',
            'pickup_plain' => 'Pickup',
            'eride_delivery' => 'E-Ride delivery',
            'seller_delivers' => 'Seller delivers',
            'since' => 'On E-Ride since :date',
            'verified' => 'E-Ride Verified',
            'preview' => 'Free preview',
            'back_to_shop' => 'See the shop',
            'item_gone' => 'This item is no longer available.',
            'shop_gone' => 'This shop is no longer on E-Ride Market.',
            'get_title' => 'Chat with :shop in the E-Ride app',
            'get_body' => 'Install E-Ride, sign in with your phone number, and this item opens by itself so you can chat with the seller.',
            'get_also' => 'With E-Ride you also get',
            'get_delivery' => 'Delivery of what you buy, by an E-Ride driver',
            'get_rides' => 'Rides in Bamenda, now or booked ahead',
            'get_shop' => 'Your own shop, free to open',
            'get_play' => 'Get it on Google Play',
            'get_have' => 'I already have E-Ride: open the item',
            'back_item' => 'Back to the item',
            'market' => 'E-Ride Market',
            'auction' => 'Auction',
            'current_bid' => 'Current bid',
            'starting_price' => 'Starting price',
            'bids' => ':n bids',
            'one_bid' => '1 bid',
            'ends' => 'Ends :time',
            'auction_ended' => 'This auction has ended',
            'bid_in_app' => 'Bid in the E-Ride app',
            'private_seller' => 'Private seller',
            'private_note' => 'A person selling something they own, not a shop. See the item before you pay.',
            'grade_like_new' => 'Like new',
            'grade_good' => 'Good',
            'grade_fair' => 'Fair',
            'grade_parts' => 'For parts',
            'service' => 'Service',
            'independent' => 'Independent',
            'independent_note' => 'A person offering the service themselves, not a business. Agree on the price before the job and pay once it is done.',
            'ask_quote' => 'Ask for a quote',
            'price_fixed' => ':price',
            'price_from' => 'From :price',
            'price_hour' => ':price / hour',
            'takes_about' => 'About :time',
            'where_place' => 'At their place',
            'where_client' => 'Comes to you',
            'where_online' => 'Online or by phone',
            'shed_title' => 'Find the shed',
            'shed_walk' => 'Get the app to be guided to the shed',
            'shed_checked' => 'Shed checked by E-Ride',
            'sells' => 'Sells',
            'shop_get_title' => 'See :shop in the E-Ride app',
            'shop_get_body' => 'Install E-Ride, sign in with your phone number, and this shop opens by itself: its items, chat with the seller, and the way to the shed.',
            'get_have_shop' => 'I already have E-Ride: open the shop',
            'back_shop' => 'Back to the shop',
            'sk_hair_beauty' => 'Hair & beauty',
            'sk_repairs' => 'Repairs',
            'sk_tailoring' => 'Tailoring',
            'sk_cleaning' => 'Cleaning',
            'sk_lessons' => 'Lessons',
            'sk_events' => 'Events',
            'sk_photography' => 'Photography',
            'sk_other' => 'Other',
            'stay_safe_service' => 'Stay safe: agree on the price and the time in the app, pay only once the job is done, and never pay a "booking fee" in advance.',
            'book_in_app' => 'Book a time in the E-Ride app',
            'get_title_service' => 'Book :shop in the E-Ride app',
            'get_body_service' => 'Install E-Ride, sign in with your phone number, and this service opens by itself so you can book a free time or ask for a price.',
            'quote_in_app' => 'Ask for a quote in the E-Ride app',
        ];
        if (!$fr) {
            return $en;
        }
        return array_merge($en, [
            'lang' => 'fr',
            'open_app' => 'Ouvrir dans l’app',
            'chat' => 'Discuter avec le vendeur dans E-Ride',
            'no_phone' => 'Pas de numéro ici : la discussion se fait dans l’app',
            'report' => 'Signaler',
            'scam_notice' => 'Les vendeurs d’E-Ride Market sont indépendants. E-Ride ne garde pas votre argent : voyez l’article avant de payer.',
            'stay_safe' => 'Prudence : rencontrez-vous dans un lieu public ou utilisez la livraison E-Ride, vérifiez l’article avant de payer, et n’envoyez jamais d’argent pour « réserver » un article.',
            'digital_warning_title' => 'Produits numériques : soyez très prudent',
            'digital_warning' => 'Cette boutique est nouvelle et pas encore vérifiée. Regardez d’abord l’aperçu. Vous payez le vendeur directement et E-Ride ne peut pas récupérer votre argent. Ne payez jamais pour des fuites d’examen, des « investissements » ou des offres d’emploi : ce sont des arnaques.',
            'items' => ':n articles',
            'one_item' => '1 article',
            'no_items' => 'Pas encore d’articles.',
            'search_shop' => 'Chercher dans cette boutique',
            'search' => 'Chercher',
            'no_match' => 'Rien ici ne correspond à « :q ».',
            'found' => ':n trouvé(s)',
            'show_all' => 'Voir tous les articles',
            'all' => 'Tout',
            'sort_new' => 'Les plus récents',
            'sort_low' => 'Prix le plus bas',
            'sort_high' => 'Prix le plus haut',
            'in_stock' => 'Masquer les épuisés',
            'no_filter_match' => 'Rien ici ne correspond à ces choix.',
            'cat_fashion' => 'Mode', 'cat_phones' => 'Téléphones', 'cat_electronics' => 'Électronique', 'cat_home' => 'Maison', 'cat_food' => 'Alimentation',
            'cat_beauty' => 'Beauté', 'cat_kids' => 'Enfants', 'cat_vehicle_parts' => 'Pièces auto et moto', 'cat_services' => 'Services',
            'cat_digital' => 'Numérique', 'cat_other' => 'Autre',
            'negotiable' => 'Prix à débattre',
            'ask_price' => 'Demandez au vendeur',
            'new' => 'Neuf',
            'used' => 'Occasion',
            'digital' => 'Produit numérique',
            'left' => 'Il en reste :n',
            'sold_out' => 'Épuisé',
            'pickup' => 'Retrait à :area',
            'pickup_plain' => 'Retrait',
            'eride_delivery' => 'Livraison E-Ride',
            'seller_delivers' => 'Le vendeur livre',
            'since' => 'Sur E-Ride depuis :date',
            'verified' => 'Vérifié par E-Ride',
            'preview' => 'Aperçu gratuit',
            'back_to_shop' => 'Voir la boutique',
            'item_gone' => 'Cet article n’est plus disponible.',
            'shop_gone' => 'Cette boutique n’est plus sur E-Ride Market.',
            'get_title' => 'Discutez avec :shop dans l’app E-Ride',
            'get_body' => 'Installez E-Ride, connectez-vous avec votre numéro, et cet article s’ouvre tout seul pour que vous discutiez avec le vendeur.',
            'get_also' => 'Avec E-Ride, vous avez aussi',
            'get_delivery' => 'La livraison de vos achats par un chauffeur E-Ride',
            'get_rides' => 'Des courses à Bamenda, tout de suite ou programmées',
            'get_shop' => 'Votre propre boutique, gratuite',
            'get_play' => 'Télécharger sur Google Play',
            'get_have' => 'J’ai déjà E-Ride : ouvrir l’article',
            'back_item' => 'Retour à l’article',
            'auction' => 'Enchère',
            'current_bid' => 'Enchère actuelle',
            'starting_price' => 'Prix de départ',
            'bids' => ':n enchères',
            'one_bid' => '1 enchère',
            'ends' => 'Fin :time',
            'auction_ended' => 'Cette enchère est terminée',
            'bid_in_app' => 'Enchérir dans l’app E-Ride',
            'private_seller' => 'Particulier',
            'private_note' => 'Une personne qui vend un objet lui appartenant, pas une boutique. Voyez l’article avant de payer.',
            'grade_like_new' => 'Comme neuf',
            'grade_good' => 'Bon état',
            'grade_fair' => 'État correct',
            'grade_parts' => 'Pour pièces',
            'service' => 'Service',
            'independent' => 'Indépendant',
            'independent_note' => 'Une personne qui propose le service elle-même, pas une entreprise. Mettez-vous d’accord sur le prix avant et payez une fois le travail fait.',
            'ask_quote' => 'Demander un devis',
            'price_from' => 'À partir de :price',
            'price_hour' => ':price / heure',
            'takes_about' => 'Environ :time',
            'where_place' => 'Chez le prestataire',
            'where_client' => 'Se déplace chez vous',
            'where_online' => 'En ligne ou par téléphone',
            'shed_title' => 'Trouver le comptoir',
            'shed_walk' => 'Prenez l\'appli pour être guidé jusqu\'au comptoir',
            'shed_checked' => 'Comptoir vérifié par E-Ride',
            'sells' => 'Vend',
            'shop_get_title' => 'Voir :shop dans l\'appli E-Ride',
            'shop_get_body' => 'Installez E-Ride, connectez-vous avec votre numéro, et la boutique s\'ouvre toute seule : ses articles, la discussion avec le vendeur et le chemin jusqu\'au comptoir.',
            'get_have_shop' => 'J\'ai déjà E-Ride : ouvrir la boutique',
            'back_shop' => 'Retour à la boutique',
            'sk_hair_beauty' => 'Coiffure et beauté',
            'sk_repairs' => 'Réparations',
            'sk_tailoring' => 'Couture',
            'sk_cleaning' => 'Ménage',
            'sk_lessons' => 'Cours',
            'sk_events' => 'Événements',
            'sk_photography' => 'Photographie',
            'sk_other' => 'Autre',
            'stay_safe_service' => 'Restez prudent : convenez du prix et de l’heure dans l’app, payez seulement une fois le travail fait, et ne payez jamais de « frais de réservation » à l’avance.',
            'book_in_app' => 'Réserver dans l’app E-Ride',
            'get_title_service' => 'Réservez :shop dans l’app E-Ride',
            'get_body_service' => 'Installez E-Ride, connectez-vous avec votre numéro, et ce service s’ouvre tout seul pour réserver un créneau libre ou demander un prix.',
            'quote_in_app' => 'Demander un devis dans l’app E-Ride',
        ]);
    }
}
