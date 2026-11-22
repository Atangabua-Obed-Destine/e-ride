<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Knowledge for Ask E-Ride: zama (reselling other shops' items) in E-Ride Market,
 * for resellers and for owners. The team can reword it in the admin.
 */
return new class extends Migration {
    private const KEYS = ['market_zama'];

    public function up(): void
    {
        if (!Schema::hasTable('assistant_entries') || DB::table('assistant_entries')->where('help_key', 'market_zama')->exists()) {
            return;
        }
        $now = now();
        $row = [
            'id' => (string)Str::uuid(), 'help_key' => 'market_zama', 'is_active' => true,
            'sort' => (int)DB::table('assistant_entries')->max('sort') + 1, 'created_at' => $now, 'updated_at' => $now,
            'service' => 'market', 'audience' => 'customer',
            'title_en' => 'Zama: selling other shops\' items at your price',
            'body_en' => "In E-Ride Market, a shop can let resellers sell its items (zama). It sets the price it wants from resellers.\n"
                . "To resell: open Market, tap Zama, choose an item and tap Sell this. It goes into your own shop, with your price or Ask for price. Buyers see your shop, never the other shop.\n"
                . "Agree a price with each buyer in the chat: tap Send a price. Each buyer can get a different price. The buyer accepts or offers their own.\n"
                . "Then ask the shop to keep it, in one of two ways: you collect it, pay the shop its price and deliver it yourself; or the buyer goes to the shop with a code and pays your price there, and the shop sends you the difference by Mobile Money.\n"
                . "To let resellers sell your item: edit it and switch on Let resellers sell it, with your price for them. Follow the requests in My shop, under Zama.\n"
                . "E-Ride holds no money in the Market: it keeps the record of the price, the code and the zama owed, and shows how each shop pays its resellers.",
            'title_fr' => 'Zama : vendre les articles d\'autres boutiques à votre prix',
            'body_fr' => "Dans le Marché E-Ride, une boutique peut laisser des revendeurs vendre ses articles (zama). Elle fixe le prix qu'elle veut des revendeurs.\n"
                . "Pour revendre : ouvrez le Marché, touchez Zama, choisissez un article et touchez Vendre ceci. Il va dans votre boutique, avec votre prix ou Demander le prix. Les acheteurs voient votre boutique, jamais l'autre.\n"
                . "Mettez-vous d'accord avec chaque acheteur dans la discussion : touchez Envoyer un prix. Chaque acheteur peut avoir un prix différent. L'acheteur accepte ou propose le sien.\n"
                . "Demandez ensuite à la boutique de le garder, de deux façons : vous le récupérez, payez son prix à la boutique et le livrez vous-même ; ou l'acheteur va à la boutique avec un code et y paie votre prix, puis la boutique vous envoie la différence par Mobile Money.\n"
                . "Pour laisser des revendeurs vendre votre article : modifiez-le et activez Laisser des revendeurs le vendre, avec votre prix pour eux. Suivez les demandes dans Ma boutique, sous Zama.\n"
                . "E-Ride ne garde pas d'argent dans le Marché : il garde la trace du prix, du code et du zama dû, et montre comment chaque boutique paie ses revendeurs.",
            'search_words' => 'zama, resell, reseller, sell for others, sell other people items, commission, margin, my price, ask for price, send a price, buyer code, revendre, revendeur, vendre pour les autres, marge, mon prix, code acheteur',
        ];
        if (!Schema::hasColumn('assistant_entries', 'audience')) {
            unset($row['audience']);
        }
        DB::table('assistant_entries')->insert($row);
    }

    public function down(): void
    {
        DB::table('assistant_entries')->whereIn('help_key', self::KEYS)->delete();
    }
};
