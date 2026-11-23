<?php

namespace Modules\HotelManagement\Http\Controllers\Web;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\HotelManagement\Service\RoomService;
use Modules\MarketManagement\Lib\MarketLinks;

/** /r/TOKEN: a room's QR card for room service. Opens the app on the hotel restaurant's menu with the room filled in. */
class RoomCardController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        $fr = str_starts_with(strtolower((string)$request->getPreferredLanguage(['en', 'fr'])), 'fr');
        $card = app(RoomService::class)->forToken($token, null);
        $t = $fr ? [
            'lang' => 'fr', 'title' => 'Service en chambre', 'room' => 'Chambre', 'open' => "Commander dans l'appli E-Ride",
            'get' => "Pas encore l'appli ? Installez E-Ride", 'pay' => 'Payez par Mobile Money, ou sur votre note de chambre si vous avez réservé avec E-Ride.',
            'gone' => "Cette carte n'est plus valable. Demandez à la réception.", 'by' => 'Le personnel du restaurant vous l\'apporte à la porte.',
        ] : [
            'lang' => 'en', 'title' => 'Room service', 'room' => 'Room', 'open' => 'Order in the E-Ride app',
            'get' => 'No app yet? Get E-Ride', 'pay' => 'Pay by Mobile Money, or on your room bill if you booked with E-Ride.',
            'gone' => 'This card is no longer valid. Ask the reception.', 'by' => 'The restaurant staff bring it to your door.',
        ];
        if (!$card) {
            return response()->view('hotelmanagement::web.room-card', ['t' => $t, 'card' => null], 404)->header('X-Robots-Tag', 'noindex');
        }
        $play = MarketLinks::playStore('r:' . $card['token']);
        return response()->view('hotelmanagement::web.room-card', [
            't' => $t,
            'card' => $card,
            'openApp' => MarketLinks::openInApp('/r/' . $card['token'], $play),
            'playUrl' => $play,
        ])->header('X-Robots-Tag', 'noindex');
    }
}
