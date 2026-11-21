<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Knowledge for Ask E-Ride: "the shop brings it" (customers) and "shops that
 * choose you" (riders). The team can reword both in the admin.
 */
return new class extends Migration {
    private const KEYS = ['food_shop_brings', 'driver_store_teams'];

    public function up(): void
    {
        if (!Schema::hasTable('assistant_entries')) {
            return;
        }
        $now = now();
        $audience = Schema::hasColumn('assistant_entries', 'audience');
        $sort = (int)DB::table('assistant_entries')->max('sort');
        $entries = [
            'food_shop_brings' => [
                'service' => 'food', 'audience' => 'customer',
                'title_en' => 'When the shop brings your order itself',
                'body_en' => "When you are very close to a shop or restaurant, it may send one of its own staff with your order instead of an E-Ride rider.\n"
                    . "The shop decides when it confirms your order. You see it before you pay, with the delivery price, which is never more than a rider would cost.\n"
                    . "Once you have paid, the app shows a code. Give it to the person who brings your order, when it is in your hands.\n"
                    . "There is no map to follow for these orders. When the staff have left, you can also tap that you have received your order. Call the shop from the order page if you need to.",
                'title_fr' => 'Quand la boutique apporte elle-même votre commande',
                'body_fr' => "Quand vous êtes tout près d'une boutique ou d'un restaurant, il peut envoyer un membre de son personnel avec votre commande, à la place d'un livreur E-Ride.\n"
                    . "La boutique décide en confirmant votre commande. Vous le voyez avant de payer, avec le prix de la livraison, qui n'est jamais plus élevé que celui d'un livreur.\n"
                    . "Une fois que vous avez payé, l'application affiche un code. Donnez-le à la personne qui apporte votre commande, quand vous l'avez en main.\n"
                    . "Il n'y a pas de carte à suivre pour ces commandes. Quand le personnel est parti, vous pouvez aussi indiquer que vous avez reçu votre commande. Appelez la boutique depuis la page de la commande au besoin.",
                'search_words' => 'shop brings it, staff delivery, own delivery, no rider, code at the door, door code, received my order, la boutique apporte, personnel, sans livreur, code, reçu ma commande',
            ],
            'driver_store_teams' => [
                'service' => 'driver', 'audience' => 'driver',
                'title_en' => 'Shops that choose you for their deliveries',
                'body_en' => "A shop or restaurant can keep a list of the riders it trusts. When a shop adds you, you get a notification.\n"
                    . "Its deliveries are then offered to you before the other riders. Some shops call only their own riders.\n"
                    . "Stay online near the shop to get them. You accept or ignore each request as usual.\n"
                    . "See the shops in the menu, under Shops I deliver for. You can call a shop there, or leave its list.\n"
                    . "Shops add riders who delivered for them well and on time, or riders whose E-Ride number they know.",
                'title_fr' => 'Les boutiques qui vous choisissent pour leurs livraisons',
                'body_fr' => "Une boutique ou un restaurant peut garder une liste des livreurs en qui il a confiance. Quand une boutique vous ajoute, vous recevez une notification.\n"
                    . "Ses livraisons vous sont alors proposées avant les autres livreurs. Certaines boutiques n'appellent que leurs propres livreurs.\n"
                    . "Restez en ligne près de la boutique pour les recevoir. Vous acceptez ou ignorez chaque demande comme d'habitude.\n"
                    . "Retrouvez ces boutiques dans le menu, sous Boutiques pour lesquelles je livre. Vous pouvez y appeler une boutique ou quitter sa liste.\n"
                    . "Les boutiques ajoutent les livreurs qui les ont bien servies et à l'heure, ou ceux dont elles connaissent le numéro E-Ride.",
                'search_words' => 'my riders, chosen riders, shop added me, shops I deliver for, deliver for a shop, priority deliveries, leave a shop, mes livreurs, boutique m a ajouté, boutiques pour lesquelles je livre, quitter une boutique',
            ],
        ];
        foreach ($entries as $key => $entry) {
            if (DB::table('assistant_entries')->where('help_key', $key)->exists()) {
                continue;
            }
            $row = ['id' => (string)Str::uuid(), 'help_key' => $key, 'is_active' => true, 'sort' => ++$sort, 'created_at' => $now, 'updated_at' => $now] + $entry;
            if (!$audience) {
                unset($row['audience']);
            }
            DB::table('assistant_entries')->insert($row);
        }
    }

    public function down(): void
    {
        DB::table('assistant_entries')->whereIn('help_key', self::KEYS)->delete();
    }
};
