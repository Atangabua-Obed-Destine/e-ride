<?php

namespace Modules\StoreManagement\Http\Controllers\Web;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\StoreManagement\Entities\Store;
use Modules\StoreManagement\Lib\StoreBranches;
use Modules\StoreManagement\Lib\StoreLinks;
use Modules\StoreManagement\Lib\StoreSettings;
use Modules\StoreManagement\Transformers\StorePresenter;

/**
 * A partner's public store page ([domain]/f/<slug>), opened from WhatsApp
 * without the app: the menu or shelves with prices, open now or not, how to
 * get an order, and "Order in the E-Ride app".
 */
class StorePageController extends Controller
{
    public function page(Request $request, string $slug): Response
    {
        $t = $this->words($request);
        $brand = Store::where('slug', $slug)->whereNull('parent_id')->first();
        if (!$brand || !$brand->is_active || !StoreSettings::enabled()) {
            return response()->view('storemanagement::web.store-gone', ['t' => $t], 404)->header('X-Robots-Tag', 'noindex');
        }
        StoreLinks::visit($brand, $request->ip() . '|' . $request->userAgent());
        $pick = StoreBranches::pick($brand, null, null);
        $kitchen = $pick['kitchen'] ?? $brand;
        $brand->load(['categories', 'items']);
        $menu = StorePresenter::menu($brand, now(), $kitchen);
        $hours = $kitchen->hoursFor(now());
        $cover = $brand->coverUrl() ?? $brand->logoUrl();
        return response()->view('storemanagement::web.store', [
            't' => $t,
            'store' => $brand,
            'kitchen' => $kitchen,
            'branches' => $brand->kitchens(true)->count(),
            'openNow' => $kitchen->isOpenNow(),
            'hours' => $hours,
            'menu' => $menu,
            'ways' => $kitchen->ways(),
            'url' => StoreLinks::url($brand),
            'ogImage' => $cover,
            'openApp' => StoreLinks::openInApp($brand),
            'getApp' => route('store.get-app', ['slug' => $brand->slug]),
        ]);
    }

    /** No app yet: what E-Ride is, and the Play Store link that opens this store after installing. */
    public function getApp(Request $request, string $slug): Response
    {
        $t = $this->words($request);
        $brand = Store::where('slug', $slug)->whereNull('parent_id')->first();
        if (!$brand || !$brand->is_active) {
            return response()->view('storemanagement::web.store-gone', ['t' => $t], 404)->header('X-Robots-Tag', 'noindex');
        }
        return response()->view('storemanagement::web.store-get-app', [
            't' => $t,
            'store' => $brand,
            'url' => StoreLinks::url($brand),
            'playUrl' => StoreLinks::playStore($brand),
            'openApp' => \Modules\MarketManagement\Lib\MarketLinks::openInApp('/f/' . $brand->slug, StoreLinks::playStore($brand)),
        ])->header('X-Robots-Tag', 'noindex');
    }

    protected function words(Request $request): array
    {
        $fr = str_starts_with(strtolower((string)$request->getPreferredLanguage(['en', 'fr'])), 'fr');
        $en = [
            'lang' => 'en',
            'market' => 'E-Ride',
            'open_app' => 'Open in the app',
            'order_app' => 'Order in the E-Ride app',
            'restaurant' => 'Restaurant',
            'shop' => 'Shop',
            'partner' => 'E-Ride partner',
            'open_now' => 'Open now',
            'open_until' => 'Open until :time',
            'closed' => 'Closed now',
            'closed_today' => 'Closed today',
            'opens' => 'Open :from to :to',
            'branches' => ':n branches',
            'way_delivery' => 'Delivery',
            'way_dine_in' => 'Eat there',
            'way_pickup' => 'Collect',
            'pay' => 'You pay in the app with MTN MoMo or Orange Money, once the store confirms your order.',
            'sold_out' => 'Sold out today',
            'later' => 'Later today',
            'not_today' => 'Not served today',
            'gone' => 'This store is not on E-Ride right now.',
            'get_title' => 'Order from :store in the E-Ride app',
            'get_body' => 'Install E-Ride, sign in with your phone number, and :store opens by itself. Order for delivery, to eat there, or to collect.',
            'get_also' => 'With E-Ride you also get',
            'get_rides' => 'Rides in Bamenda, now or booked ahead',
            'get_market' => 'E-Ride Market: things to buy near you',
            'get_parcel' => 'Parcels sent across town',
            'get_play' => 'Get it on Google Play',
            'get_have' => 'I already have E-Ride: open the store',
            'back' => 'Back to the menu',
            'items' => ':n items',
        ];
        $frWords = [
            'lang' => 'fr',
            'open_app' => 'Ouvrir dans l\'appli',
            'order_app' => 'Commander dans l\'appli E-Ride',
            'restaurant' => 'Restaurant',
            'shop' => 'Boutique',
            'partner' => 'Partenaire E-Ride',
            'open_now' => 'Ouvert',
            'open_until' => 'Ouvert jusqu\'à :time',
            'closed' => 'Fermé',
            'closed_today' => 'Fermé aujourd\'hui',
            'opens' => 'Ouvert de :from à :to',
            'branches' => ':n adresses',
            'way_delivery' => 'Livraison',
            'way_dine_in' => 'Sur place',
            'way_pickup' => 'À emporter',
            'pay' => 'Vous payez dans l\'appli par MTN MoMo ou Orange Money, après la confirmation du magasin.',
            'sold_out' => 'Épuisé aujourd\'hui',
            'later' => 'Plus tard aujourd\'hui',
            'not_today' => 'Pas servi aujourd\'hui',
            'gone' => 'Ce magasin n\'est pas sur E-Ride pour le moment.',
            'get_title' => 'Commandez chez :store dans l\'appli E-Ride',
            'get_body' => 'Installez E-Ride, connectez-vous avec votre numéro, et :store s\'ouvre tout seul. Commandez en livraison, sur place ou à emporter.',
            'get_also' => 'Avec E-Ride vous avez aussi',
            'get_rides' => 'Des courses à Bamenda, tout de suite ou à l\'avance',
            'get_market' => 'E-Ride Market : des achats près de chez vous',
            'get_parcel' => 'Des colis envoyés en ville',
            'get_play' => 'Disponible sur Google Play',
            'get_have' => 'J\'ai déjà E-Ride : ouvrir le magasin',
            'back' => 'Retour au menu',
            'items' => ':n articles',
        ];
        return $fr ? $frWords + $en : $en;
    }
}
