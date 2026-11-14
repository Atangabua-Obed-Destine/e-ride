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
        $items = MarketItem::with('photos')->where('shop_id', $shop->id)->whereIn('status', MarketItem::PUBLIC)->latest()->limit(60)->get();
        $cover = $items->first()?->cover();
        return response()->view('marketmanagement::web.shop', [
            't' => $t,
            'shop' => $shop,
            'items' => $items,
            'url' => MarketLinks::shop($shop),
            'ogImage' => $cover?->ogUrl() ?? $shop->logoUrl(),
            'openApp' => MarketLinks::openInApp('/s/' . $shop->slug, MarketLinks::playStore('s:' . $shop->slug)),
        ]);
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

    private function unavailable(array $t, string $message, ?MarketShop $shop, int $status): Response
    {
        return response()->view('marketmanagement::web.unavailable', [
            't' => $t, 'message' => $message, 'shop' => $shop, 'shopUrl' => $shop ? MarketLinks::shop($shop) : null,
        ], $status)->header('X-Robots-Tag', 'noindex');
    }

    /** The page's words, English or French from the browser. */
    private function words(Request $request): array
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
        ]);
    }
}
