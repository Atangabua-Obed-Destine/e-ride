<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Help for drivers: each knowledge entry is for customers or for drivers. The
 * driver app shows the driver entries as info sheets (by help key) and Ask
 * E-Ride answers drivers from them only. Questions record who asked.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('assistant_entries', function (Blueprint $t) {
            if (!Schema::hasColumn('assistant_entries', 'audience')) {
                $t->string('audience', 10)->default('customer')->after('id')->index();
            }
        });
        Schema::table('assistant_questions', function (Blueprint $t) {
            if (!Schema::hasColumn('assistant_questions', 'audience')) {
                $t->string('audience', 10)->default('customer')->after('user_id')->index();
            }
        });

        if (DB::table('assistant_entries')->where('audience', 'driver')->exists()) {
            return;
        }
        $now = now();
        $sort = (int)DB::table('assistant_entries')->max('sort') + 1;
        foreach (self::driverPack() as $i => $e) {
            DB::table('assistant_entries')->insert($e + [
                'id' => (string)Str::uuid(), 'audience' => 'driver', 'is_active' => true,
                'sort' => $sort + $i, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('assistant_entries')->where('audience', 'driver')->delete();
        Schema::table('assistant_entries', fn(Blueprint $t) => $t->dropColumn('audience'));
        Schema::table('assistant_questions', fn(Blueprint $t) => $t->dropColumn('audience'));
    }

    /** What drivers ask most, in plain words. Entries with a help key show as an info sheet in the driver app. */
    private static function driverPack(): array
    {
        $e = fn(?string $key, string $service, array $en, array $fr) => [
            'help_key' => $key, 'service' => $service,
            'title_en' => $en[0], 'body_en' => implode("\n", $en[1]),
            'title_fr' => $fr[0], 'body_fr' => implode("\n", $fr[1]),
        ];
        return [
            $e('driver_online', 'driver', ['Going online', [
                'Switch on at the top of Home to receive ride and parcel requests.',
                'Keep the app open and your location on, so E-Ride can send you the requests near you.',
                'Switch off when you stop: you will not receive requests.',
            ]], ['Passer en ligne', [
                'Activez l\'interrupteur en haut de l\'accueil pour recevoir des demandes de course et de colis.',
                'Gardez l\'appli ouverte et la localisation activée, pour qu\'E-Ride vous envoie les demandes proches.',
                'Désactivez-le quand vous arrêtez : vous ne recevrez plus de demandes.',
            ]]),
            $e('driver_zone', 'driver', ['Your zone', [
                'You receive requests only inside your E-Ride zone.',
                'If you drive out of it, the app warns you and shows the way back.',
                'Requests come again once you are back in the zone.',
            ]], ['Votre zone', [
                'Vous recevez des demandes uniquement dans votre zone E-Ride.',
                'Si vous en sortez, l\'appli vous prévient et vous montre le chemin du retour.',
                'Les demandes reviennent dès que vous êtes de retour dans la zone.',
            ]]),
            $e('driver_face', 'driver', ['Face verification', [
                'E-Ride may ask you to confirm it is you with a selfie.',
                'Until you do, your account can stay paused.',
                'Use good light, remove sunglasses and look straight at the camera.',
            ]], ['Vérification du visage', [
                'E-Ride peut vous demander de confirmer votre identité avec un selfie.',
                'Tant que ce n\'est pas fait, votre compte peut rester suspendu.',
                'Mettez-vous dans un endroit éclairé, retirez vos lunettes de soleil et regardez la caméra.',
            ]]),
            $e('driver_earnings', 'driver', ['Your earnings', [
                'Fares paid in the app go to your E-Ride balance, after E-Ride\'s commission.',
                'Add your Mobile Money details in Add withdraw info, in the menu.',
                'Ask for a withdrawal from your wallet, and E-Ride sends it to your Mobile Money.',
            ]], ['Vos gains', [
                'Les courses payées dans l\'appli vont sur votre solde E-Ride, après la commission d\'E-Ride.',
                'Ajoutez vos coordonnées Mobile Money dans Infos de retrait, dans le menu.',
                'Demandez un retrait depuis votre portefeuille, et E-Ride l\'envoie sur votre Mobile Money.',
            ]]),
            $e('driver_cash_limit', 'driver', ['Cash in hand', [
                'When a customer pays you in cash, you keep E-Ride\'s commission from that cash.',
                'Above the limit E-Ride sets, your account pauses until you pay E-Ride what you hold.',
                'Pay it from your wallet, and you can go online again.',
            ]], ['Espèces en main', [
                'Quand un client vous paie en espèces, vous gardez la commission d\'E-Ride sur cet argent.',
                'Au-delà de la limite fixée par E-Ride, votre compte est suspendu jusqu\'à ce que vous payiez E-Ride.',
                'Payez depuis votre portefeuille, et vous pouvez repasser en ligne.',
            ]]),
            $e('driver_deposit', 'driver', ['Booking deposits', [
                'Some customers pay a deposit after you accept. Wait for it before you set off: the app shows when it is paid.',
                'The deposit counts toward the fare.',
                'If the customer cancels late, you receive part of it.',
            ]], ['Acomptes de réservation', [
                'Certains clients paient un acompte après votre acceptation. Attendez-le avant de partir : l\'appli indique quand il est payé.',
                'L\'acompte compte dans le prix de la course.',
                'Si le client annule tard, vous en recevez une partie.',
            ]]),
            $e('driver_trip_code', 'driver', ['The trip code', [
                'When E-Ride asks for it, the customer gives you a short code at pickup.',
                'Enter it in the app to start the trip.',
                'It proves you picked up the right customer.',
            ]], ['Le code de course', [
                'Quand E-Ride le demande, le client vous donne un code court au départ.',
                'Saisissez-le dans l\'appli pour commencer la course.',
                'Il prouve que vous avez pris le bon client.',
            ]]),
            $e(null, 'driver', ['Ride requests and health rides', [
                'A request shows the pickup, the destination and the fare. Accept quickly: the first driver to accept gets it.',
                'Urgent health rides to a hospital, clinic or pharmacy are marked in red: go straight to the customer.',
                'Scheduled rides are accepted ahead of time; E-Ride reminds you before the pickup.',
            ]], ['Demandes de course et courses santé', [
                'Une demande indique le départ, la destination et le prix. Acceptez vite : le premier chauffeur qui accepte l\'obtient.',
                'Les courses santé urgentes vers un hôpital, une clinique ou une pharmacie sont en rouge : allez directement au client.',
                'Les courses programmées sont acceptées à l\'avance ; E-Ride vous le rappelle avant le départ.',
            ]]),
            $e(null, 'driver', ['Parcels and food deliveries', [
                'For a food order, collect it at the restaurant shown: it is already paid.',
                'At delivery, take the photos the app asks for.',
                'If the receiver is not there, follow the app to return the parcel to the sender.',
            ]], ['Colis et livraisons de repas', [
                'Pour une commande de repas, récupérez-la au restaurant indiqué : elle est déjà payée.',
                'À la livraison, prenez les photos demandées par l\'appli.',
                'Si le destinataire est absent, suivez l\'appli pour rapporter le colis à l\'expéditeur.',
            ]]),
            $e(null, 'driver', ['Moves', [
                'A move is booked on a tricycle, pickup or truck.',
                'You agree the price with the customer, and they pay you directly.',
            ]], ['Déménagements', [
                'Un déménagement est réservé sur un tricycle, un pick-up ou un camion.',
                'Vous fixez le prix avec le client, et il vous paie directement.',
            ]]),
            $e(null, 'driver', ['Safety and lost items', [
                'Report danger from Home: gunshots, a roadblock or a protest warn E-Ride and other drivers.',
                'Areas E-Ride pauses for safety show on your map; avoid them if you can.',
                'An item left in your car shows in Lost items, in the menu: answer the customer there.',
            ]], ['Sécurité et objets perdus', [
                'Signalez un danger depuis l\'accueil : coups de feu, barrage ou manifestation préviennent E-Ride et les autres chauffeurs.',
                'Les zones suspendues par E-Ride pour la sécurité apparaissent sur votre carte ; évitez-les si possible.',
                'Un objet oublié dans votre voiture apparaît dans Objets perdus, dans le menu : répondez au client là-bas.',
            ]]),
            $e(null, 'driver', ['Problems with money, a trip or your account', [
                'For any problem with money, a trip or your account, contact E-Ride support on WhatsApp.',
            ]], ['Problèmes d\'argent, de course ou de compte', [
                'Pour tout problème d\'argent, de course ou de compte, contactez le support E-Ride sur WhatsApp.',
            ]]),
        ];
    }
};
