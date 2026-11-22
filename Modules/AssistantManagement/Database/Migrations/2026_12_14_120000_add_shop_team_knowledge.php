<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Knowledge for Ask E-Ride: a shop's team (staff) in E-Ride Market. The team can reword it in the admin. */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('assistant_entries') || DB::table('assistant_entries')->where('help_key', 'market_shop_team')->exists()) {
            return;
        }
        $now = now();
        $row = [
            'id' => (string)Str::uuid(), 'help_key' => 'market_shop_team', 'is_active' => true,
            'sort' => (int)DB::table('assistant_entries')->max('sort') + 1, 'created_at' => $now, 'updated_at' => $now,
            'service' => 'market', 'audience' => 'customer',
            'title_en' => 'People who help run your shop (team)',
            'body_en' => "A shop owner can let other people help run the shop, each with their own E-Ride account.\n"
                . "In My shop, tap Team, then Invite, and enter their E-Ride phone number. Choose Sales (chats, orders, bookings, stock, zama) or Manager (also editing items and settings). You can tick or untick each permission.\n"
                . "They accept in their own app, on the Market screen, then tap Open the shop to work for it. Buyers only ever see the shop.\n"
                . "Only the owner changes prices, posts or deletes items, changes the shop details or the team. Staff never see the owner's own purchases or chats.\n"
                . "Staff who are On duty also get the shop's notifications. The owner sees who did what, and can remove someone at any time.",
            'title_fr' => 'Les personnes qui vous aident à tenir la boutique (équipe)',
            'body_fr' => "Un propriétaire de boutique peut laisser d'autres personnes l'aider, chacune avec son propre compte E-Ride.\n"
                . "Dans Ma boutique, touchez Équipe, puis Inviter, et entrez son numéro E-Ride. Choisissez Vente (discussions, commandes, réservations, stock, zama) ou Gérant (aussi la modification des articles et des réglages). Vous pouvez cocher ou décocher chaque permission.\n"
                . "La personne accepte dans sa propre application, sur l'écran du Marché, puis touche Ouvrir la boutique pour y travailler. Les acheteurs ne voient que la boutique.\n"
                . "Seul le propriétaire change les prix, publie ou supprime des articles, change les détails de la boutique ou l'équipe. L'équipe ne voit jamais les achats ni les discussions personnels du propriétaire.\n"
                . "Les personnes En service reçoivent aussi les notifications de la boutique. Le propriétaire voit qui a fait quoi et peut retirer quelqu'un à tout moment.",
            'search_words' => 'team, staff, employee, worker, manager, sales, help run my shop, permissions, invite, on duty, équipe, employé, vendeuse, gérant, aider ma boutique, permissions, inviter, en service',
        ];
        if (!Schema::hasColumn('assistant_entries', 'audience')) {
            unset($row['audience']);
        }
        DB::table('assistant_entries')->insert($row);
    }

    public function down(): void
    {
        DB::table('assistant_entries')->where('help_key', 'market_shop_team')->delete();
    }
};
