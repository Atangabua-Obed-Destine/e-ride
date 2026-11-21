<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The basics Ask E-Ride was missing, so "how do I book?", "how much?" and
 * "how do I send a parcel?" get a real answer: booking a ride, the price,
 * sending a parcel, talking to the driver, cancelling. Plain knowledge
 * entries (no info sheet, no card); the team edits them in the admin.
 */
return new class extends Migration {
    private const TITLES = ['How to book a ride', 'The price of a ride or a parcel', 'How to send a parcel', 'Talking to your driver', 'Cancelling a ride'];

    public function up(): void
    {
        if (!Schema::hasTable('assistant_entries') || DB::table('assistant_entries')->whereIn('title_en', self::TITLES)->exists()) {
            return;
        }
        $now = now();
        $sort = (int)DB::table('assistant_entries')->max('sort') + 1;
        $audience = Schema::hasColumn('assistant_entries', 'audience');
        foreach (self::entries() as $i => [$service, $en, $fr]) {
            DB::table('assistant_entries')->insert([
                'id' => (string)Str::uuid(), 'help_key' => null, 'is_active' => true, 'sort' => $sort + $i,
                'service' => $service, 'tip_action' => null,
                'title_en' => $en[0], 'body_en' => $en[1], 'title_fr' => $fr[0], 'body_fr' => $fr[1],
                'created_at' => $now, 'updated_at' => $now,
            ] + ($audience ? ['audience' => 'customer'] : []));
        }
    }

    public function down(): void
    {
        DB::table('assistant_entries')->whereIn('title_en', self::TITLES)->whereNull('help_key')->whereNull('tip_action')->delete();
    }

    private static function entries(): array
    {
        return [
            ['rides',
                ['How to book a ride',
                    "Open Ride. Your pickup is where you are; you can change it.\n"
                    . "Type or say where you are going and choose the place.\n"
                    . "Tell the driver where to find you: write it or record a voice note.\n"
                    . "Choose a vehicle: the price is shown for each one. Then confirm, and a driver comes to you.\n"
                    . "You follow the driver on the map, and you can call or message them in the app."],
                ['Comment réserver une course',
                    "Ouvrez Course. Votre point de départ est là où vous êtes ; vous pouvez le changer.\n"
                    . "Tapez ou dites où vous allez et choisissez le lieu.\n"
                    . "Dites au chauffeur où vous trouver : écrivez-le ou laissez un message vocal.\n"
                    . "Choisissez un véhicule : le prix est affiché pour chacun. Confirmez, et un chauffeur vient vous chercher.\n"
                    . "Vous suivez le chauffeur sur la carte, et vous pouvez l'appeler ou lui écrire dans l'application."]],
            ['rides',
                ['The price of a ride or a parcel',
                    "The price depends on the distance and on the vehicle you choose, so it is different for each trip.\n"
                    . "The app shows the price for each vehicle before you book: you always see it before you confirm.\n"
                    . "To see the price, open Ride (or Parcel), set where you go, and the prices appear.\n"
                    . "You pay with MTN MoMo or Orange Money."],
                ['Le prix d\'une course ou d\'un colis',
                    "Le prix dépend de la distance et du véhicule choisi : il change donc à chaque trajet.\n"
                    . "L'application affiche le prix de chaque véhicule avant la réservation : vous le voyez toujours avant de confirmer.\n"
                    . "Pour voir le prix, ouvrez Course (ou Colis), indiquez où vous allez, et les prix apparaissent.\n"
                    . "Vous payez avec MTN MoMo ou Orange Money."]],
            ['parcel',
                ['How to send a parcel',
                    "Open Parcel and choose what kind of parcel it is.\n"
                    . "Give where to pick it up, and the name, phone number and address of the person who receives it.\n"
                    . "You can record a voice note for the driver: say where to pick it up and who gets it, and E-Ride fills what it can.\n"
                    . "Add the weight, see the price, and confirm. A driver picks it up and delivers it.\n"
                    . "You follow the parcel live in the app until it is delivered."],
                ['Comment envoyer un colis',
                    "Ouvrez Colis et choisissez le type de colis.\n"
                    . "Indiquez où le récupérer, puis le nom, le numéro et l'adresse de la personne qui le reçoit.\n"
                    . "Vous pouvez laisser un message vocal au chauffeur : dites où récupérer le colis et à qui il va, et E-Ride remplit ce qu'il peut.\n"
                    . "Ajoutez le poids, voyez le prix et confirmez. Un chauffeur le récupère et le livre.\n"
                    . "Vous suivez le colis en direct dans l'application jusqu'à la livraison."]],
            ['rides',
                ['Talking to your driver',
                    "Once a driver accepts your ride or your parcel, you can call them or send them a message from the trip screen, without leaving the app.\n"
                    . "If the driver cannot find you, send a message or a voice message saying what is near you: a shop, a gate, a junction.\n"
                    . "Next time, add a pickup description or a voice note when you book: the driver gets it with your request."],
                ['Parler à votre chauffeur',
                    "Dès qu'un chauffeur accepte votre course ou votre colis, vous pouvez l'appeler ou lui écrire depuis l'écran du trajet, sans quitter l'application.\n"
                    . "Si le chauffeur ne vous trouve pas, envoyez un message ou un message vocal en disant ce qui est près de vous : une boutique, un portail, un carrefour.\n"
                    . "La prochaine fois, ajoutez une description ou un message vocal à la réservation : le chauffeur le reçoit avec votre demande."]],
            ['rides',
                ['Cancelling a ride',
                    "You can cancel a ride from the trip screen while E-Ride is looking for a driver or before the driver picks you up.\n"
                    . "If a driver is already on the way to you, a cancellation fee may apply.\n"
                    . "For a problem with money after a ride, talk to E-Ride support on WhatsApp."],
                ['Annuler une course',
                    "Vous pouvez annuler une course depuis l'écran du trajet pendant la recherche d'un chauffeur ou avant qu'il ne vous prenne.\n"
                    . "Si un chauffeur est déjà en route vers vous, des frais d'annulation peuvent s'appliquer.\n"
                    . "Pour un problème d'argent après une course, contactez le support E-Ride sur WhatsApp."]],
        ];
    }
};
