<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Ask E-Ride: the knowledge pack (short facts the assistant answers from; an entry
 * with a help_key also shows as an info sheet on that app screen) and the log of
 * what customers ask.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('assistant_entries')) {
            Schema::create('assistant_entries', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->string('help_key', 40)->nullable()->unique();
                $t->string('service', 40)->nullable();
                $t->string('title_en', 120);
                $t->string('title_fr', 120);
                $t->text('body_en');
                $t->text('body_fr');
                $t->boolean('is_active')->default(true);
                $t->integer('sort')->default(0);
                $t->timestamps();
            });
            $now = now();
            foreach (self::starterPack() as $i => $e) {
                DB::table('assistant_entries')->insert($e + ['id' => (string)Str::uuid(), 'is_active' => true, 'sort' => $i, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        if (!Schema::hasTable('assistant_questions')) {
            Schema::create('assistant_questions', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->foreignUuid('user_id')->nullable()->index();
                $t->text('question');
                $t->string('screen', 80)->nullable();
                $t->string('language', 8)->nullable();
                $t->string('status', 20)->index(); // answered, no_answer, limited, busy
                $t->string('provider', 20)->nullable();
                $t->text('reply')->nullable();
                $t->json('actions')->nullable();
                $t->boolean('helped')->nullable();
                $t->unsignedInteger('ms')->nullable();
                $t->timestamps();
                $t->index('created_at');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_questions');
        Schema::dropIfExists('assistant_entries');
    }

    /** One fact per entry, in plain words. Body lines become steps on the help sheet. */
    private static function starterPack(): array
    {
        return [
            ['help_key' => null, 'service' => 'general',
             'title_en' => 'What E-Ride does', 'title_fr' => 'Ce que fait E-Ride',
             'body_en' => "Rides now or booked for later, and urgent health rides to a hospital, clinic or pharmacy.\nParcels across town, and Move: a truck for goods.\nFood & Shops: order from restaurants and shops, delivered by an E-Ride rider.\nBus tickets with verified agencies, hotel rooms you ask the reception for, and Explore for places near you.\nMarket to buy and sell, Jobs from verified employers, and Homes to rent.",
             'body_fr' => "Des courses maintenant ou réservées pour plus tard, et des courses santé urgentes vers un hôpital, une clinique ou une pharmacie.\nDes colis en ville, et le déménagement : un camion pour vos biens.\nRepas & boutiques : commandez, un livreur E-Ride vous l'apporte.\nDes billets de bus avec des agences vérifiées, des chambres d'hôtel demandées à la réception, et Explorer pour les lieux près de vous.\nLe marché pour acheter et vendre, des emplois d'employeurs vérifiés, et des logements à louer."],
            ['help_key' => 'payments', 'service' => 'general',
             'title_en' => 'How to pay on E-Ride', 'title_fr' => 'Comment payer sur E-Ride',
             'body_en' => "You pay with MTN MoMo or Orange Money only.\nA payment page opens: confirm on your phone with your Mobile Money PIN.\nYou see the result in the app as soon as the payment arrives.",
             'body_fr' => "Vous payez uniquement par MTN MoMo ou Orange Money.\nUne page de paiement s'ouvre : confirmez sur votre téléphone avec votre code Mobile Money.\nVous voyez le résultat dans l'appli dès que le paiement arrive."],
            ['help_key' => 'food_pay', 'service' => 'food',
             'title_en' => 'How paying works for food', 'title_fr' => 'Comment payer un repas',
             'body_en' => "You send your order. Nothing is charged yet.\nThe restaurant confirms, or suggests changes you can accept or refuse.\nYou pay with MTN MoMo or Orange Money within 10 minutes, then they start cooking.",
             'body_fr' => "Vous envoyez votre commande. Rien n'est encore débité.\nLe restaurant confirme, ou propose des changements que vous acceptez ou refusez.\nVous payez par MTN MoMo ou Orange Money sous 10 minutes, puis ils cuisinent."],
            ['help_key' => 'food_branch', 'service' => 'food',
             'title_en' => 'Choosing a branch', 'title_fr' => 'Choisir une succursale',
             'body_en' => "Some restaurants have several branches. We pick the nearest open one to your address.\nTap Choose a branch to pick another. We remember your choice for next time.\nA dish sold out here may be available at another branch: we show you where.",
             'body_fr' => "Certains restaurants ont plusieurs succursales. Nous choisissons la plus proche qui est ouverte.\nTouchez Choisir une succursale pour en prendre une autre. Nous retenons votre choix.\nUn plat épuisé ici peut être disponible ailleurs : nous vous montrons où."],
            ['help_key' => 'bus_booking', 'service' => 'bus',
             'title_en' => 'Booking a bus seat', 'title_fr' => 'Réserver une place de bus',
             'body_en' => "Pick your seats and send the request.\nThe agency answers within a few minutes.\nOnce they accept, pay with Mobile Money before the time runs out, and your ticket appears.",
             'body_fr' => "Choisissez vos places et envoyez la demande.\nL'agence répond en quelques minutes.\nUne fois acceptée, payez par Mobile Money avant la fin du temps, et votre billet apparaît."],
            ['help_key' => 'hotel_kept', 'service' => 'hotels',
             'title_en' => 'A room kept for you', 'title_fr' => 'Une chambre gardée pour vous',
             'body_en' => "The reception keeps the room until the time shown. Nothing is paid in the app.\nRide there before that time. If you don't come, the room is released.\nYou pay the hotel directly when you arrive.",
             'body_fr' => "La réception garde la chambre jusqu'à l'heure indiquée. Rien n'est payé dans l'appli.\nAllez-y avant cette heure. Si vous ne venez pas, la chambre est libérée.\nVous payez l'hôtel directement à votre arrivée."],
            ['help_key' => 'ride_later', 'service' => 'rides',
             'title_en' => 'Booking a ride for later', 'title_fr' => 'Réserver une course pour plus tard',
             'body_en' => "Book from 1 hour to 3 days ahead.\nA driver accepts your ride before the time.\nYou can follow it from Your activity until the driver arrives.",
             'body_fr' => "Réservez de 1 heure à 3 jours à l'avance.\nUn chauffeur accepte votre course avant l'heure.\nVous la suivez depuis Votre activité jusqu'à l'arrivée du chauffeur."],
            ['help_key' => 'health_ride', 'service' => 'rides',
             'title_en' => 'Health rides', 'title_fr' => 'Courses santé',
             'body_en' => "An urgent ride to a hospital, clinic or pharmacy.\nDrivers see it is urgent.\nIn a life-threatening emergency, call the emergency services first.",
             'body_fr' => "Une course urgente vers un hôpital, une clinique ou une pharmacie.\nLes chauffeurs voient que c'est urgent.\nEn cas d'urgence vitale, appelez d'abord les secours."],
            ['help_key' => 'eride_id', 'service' => 'account',
             'title_en' => 'E-Ride ID verified', 'title_fr' => 'E-Ride ID vérifié',
             'body_en' => "Send a photo of your ID card or passport, and a selfie, once.\nOur team checks them.\nYou get a badge that shows who you are, until your document expires.",
             'body_fr' => "Envoyez une fois une photo de votre carte d'identité ou passeport, et un selfie.\nNotre équipe les vérifie.\nVous obtenez un badge qui montre qui vous êtes, jusqu'à l'expiration du document."],
            ['help_key' => null, 'service' => 'general',
             'title_en' => 'Refunds and problems with money', 'title_fr' => "Remboursements et problèmes d'argent",
             'body_en' => "Paid food orders are not refunded: the restaurant confirms before you pay.\nFor any problem with money, an order or a ride, contact E-Ride support on WhatsApp.",
             'body_fr' => "Les commandes de repas payées ne sont pas remboursées : le restaurant confirme avant que vous payiez.\nPour tout problème d'argent, de commande ou de course, contactez le support E-Ride sur WhatsApp."],
        ];
    }
};
