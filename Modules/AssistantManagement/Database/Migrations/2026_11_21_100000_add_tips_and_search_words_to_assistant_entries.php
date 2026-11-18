<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * "Did you know?" cards and the smart search on Home.
 *
 * An entry with a tip_action also shows as a card on Home: its title, the first
 * line of its text, and a button (tip_action is an Ask E-Ride button type, or
 * "explore:<category>" for one Explore category). search_words lead the app's
 * search to the entry's service: words the built-in list does not know yet.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('assistant_entries', function (Blueprint $t) {
            if (!Schema::hasColumn('assistant_entries', 'tip_action')) {
                $t->string('tip_action', 40)->nullable()->after('service');
                $t->string('tip_label_en', 40)->nullable()->after('tip_action');
                $t->string('tip_label_fr', 40)->nullable()->after('tip_label_en');
                $t->text('search_words')->nullable()->after('tip_label_fr');
            }
        });

        if (DB::table('assistant_entries')->whereNotNull('tip_action')->exists()) {
            return;
        }
        $now = now();
        $sort = (int)DB::table('assistant_entries')->max('sort') + 1;
        foreach (self::starterTips() as $i => $e) {
            DB::table('assistant_entries')->insert($e + [
                'id' => (string)Str::uuid(), 'help_key' => null, 'is_active' => true,
                'sort' => $sort + $i, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('assistant_entries')->whereNotNull('tip_action')->whereNull('help_key')->delete();
        Schema::table('assistant_entries', function (Blueprint $t) {
            $t->dropColumn(['tip_action', 'tip_label_en', 'tip_label_fr', 'search_words']);
        });
    }

    /** One card each: a headline, one line, and the button that opens the service. */
    private static function starterTips(): array
    {
        $tip = fn(string $service, string $action, array $en, array $fr) => [
            'service' => $service, 'tip_action' => $action,
            'title_en' => $en[0], 'body_en' => $en[1], 'tip_label_en' => $en[2],
            'title_fr' => $fr[0], 'body_fr' => $fr[1], 'tip_label_fr' => $fr[2],
            'search_words' => null,
        ];
        return [
            $tip('bus', 'bus',
                ['You can book a bus seat here', 'Pick your seat with verified agencies, then pay with Mobile Money.', 'See buses'],
                ['Réservez votre place de bus ici', 'Choisissez votre place auprès d\'agences vérifiées, puis payez par Mobile Money.', 'Voir les bus']),
            $tip('explore', 'explore:pharmacy',
                ['Find the pharmacy on duty', 'Explore shows the pharmacies open now near you, and a ride there in one tap.', 'See pharmacies'],
                ['Trouvez la pharmacie de garde', 'Explorer montre les pharmacies ouvertes près de vous, et une course pour y aller en un geste.', 'Voir les pharmacies']),
            $tip('food', 'food',
                ['Food delivered to your door', 'The restaurant confirms your order before you pay, then an E-Ride rider brings it.', 'Order food'],
                ['Vos repas livrés chez vous', 'Le restaurant confirme votre commande avant que vous payiez, puis un livreur E-Ride vous l\'apporte.', 'Commander']),
            $tip('market', 'market',
                ['Sell what you no longer use', 'List it once on E-Ride Market, then share it to your WhatsApp status in one tap.', 'Open Market'],
                ['Vendez ce que vous n\'utilisez plus', 'Publiez-le une fois sur E-Ride Market, puis partagez-le sur votre statut WhatsApp en un geste.', 'Ouvrir le marché']),
            $tip('rides', 'ride_later',
                ['Book tomorrow\'s ride today', 'Book from 1 hour to 3 days ahead: a driver accepts before the time.', 'Book for later'],
                ['Réservez dès aujourd\'hui la course de demain', 'Réservez de 1 heure à 3 jours à l\'avance : un chauffeur accepte avant l\'heure.', 'Réserver']),
            $tip('jobs', 'jobs',
                ['Jobs from verified employers', 'Apply in one tap with your Work Card. E-Ride checks every employer.', 'See jobs'],
                ['Des emplois d\'employeurs vérifiés', 'Postulez en un geste avec votre Work Card. E-Ride vérifie chaque employeur.', 'Voir les emplois']),
            $tip('rides', 'health_ride',
                ['A ride that knows it is urgent', 'Health rides to a hospital, clinic or pharmacy tell the driver it is urgent.', 'Health ride'],
                ['Une course qui sait que c\'est urgent', 'Les courses santé vers un hôpital, une clinique ou une pharmacie signalent l\'urgence au chauffeur.', 'Course santé']),
            $tip('hotels', 'explore:stay',
                ['Ask a hotel before you go', 'See the free rooms, chat with the reception, and they keep a room for you.', 'See hotels'],
                ['Demandez à l\'hôtel avant d\'y aller', 'Voyez les chambres libres, écrivez à la réception, et elle vous garde une chambre.', 'Voir les hôtels']),
            $tip('parcel', 'parcel',
                ['Send a parcel across town', 'A driver picks it up and delivers it, and you follow it live in the app.', 'Send a parcel'],
                ['Envoyez un colis en ville', 'Un chauffeur le récupère et le livre, et vous le suivez en direct dans l\'appli.', 'Envoyer un colis']),
            $tip('move', 'move',
                ['A truck for your move', 'Move books a tricycle, pickup or truck to carry your goods.', 'Book a truck'],
                ['Un camion pour votre déménagement', 'Déménagement réserve un tricycle, un pick-up ou un camion pour vos biens.', 'Réserver un camion']),
            $tip('explore', 'explore:homes',
                ['Find a place to rent', 'Rooms, studios and shops to rent, with visits you book in the app.', 'See homes'],
                ['Trouvez un logement à louer', 'Chambres, studios et boutiques à louer, avec des visites réservées dans l\'appli.', 'Voir les logements']),
            $tip('account', 'id_verify',
                ['Get your E-Ride ID badge', 'Verify once with your ID card and a selfie: sellers, hotels and agencies trust you faster.', 'Get verified'],
                ['Obtenez votre badge E-Ride ID', 'Vérifiez-vous une fois avec votre carte d\'identité et un selfie : vendeurs, hôtels et agences vous font confiance plus vite.', 'Me faire vérifier']),
        ];
    }
};
