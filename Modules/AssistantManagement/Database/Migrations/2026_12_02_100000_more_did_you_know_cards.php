<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * More "Did you know?" cards, for the services that had none: Things to do and
 * tours (the city guide), safety (trusted contacts), inviting friends and
 * coupons. No wallet card: the wallet is never offered as a way to pay.
 */
return new class extends Migration {
    private const ACTIONS = ['things', 'safety', 'refer', 'coupons'];

    public function up(): void
    {
        if (!Schema::hasTable('assistant_entries') || DB::table('assistant_entries')->whereIn('tip_action', self::ACTIONS)->exists()) {
            return;
        }
        $now = now();
        $sort = (int)DB::table('assistant_entries')->max('sort') + 1;
        $audience = Schema::hasColumn('assistant_entries', 'audience');
        foreach (self::tips() as $i => [$service, $action, $en, $fr, $words]) {
            DB::table('assistant_entries')->insert([
                'id' => (string)Str::uuid(), 'help_key' => null, 'is_active' => true, 'sort' => $sort + $i,
                'service' => $service, 'tip_action' => $action,
                'title_en' => $en[0], 'body_en' => $en[1], 'tip_label_en' => $en[2],
                'title_fr' => $fr[0], 'body_fr' => $fr[1], 'tip_label_fr' => $fr[2],
                'search_words' => $words,
                'created_at' => $now, 'updated_at' => $now,
            ] + ($audience ? ['audience' => 'customer'] : []));
        }
    }

    public function down(): void
    {
        DB::table('assistant_entries')->whereIn('tip_action', self::ACTIONS)->whereNull('help_key')->delete();
    }

    private static function tips(): array
    {
        return [
            ['explore', 'things',
                ['Things to do in your city', 'Places to eat, swim, party and see, rated by E-Ride riders. Visit any of them in one tap.', 'See things to do'],
                ['Que faire dans votre ville', 'Où manger, nager, sortir et visiter, notés par les clients E-Ride. Allez-y en un geste.', 'Voir que faire'],
                'things to do, sortie, sorties, outing, fun, weekend, swim, piscine, party, bar, club, visit, visiter, sightseeing'],
            ['explore', 'things',
                ['Take a ready-made tour', 'Tours of the city, stop by stop: each stop is a normal E-Ride ride, with the guide at every stop.', 'See tours'],
                ['Suivez un circuit tout prêt', "Des circuits de la ville, étape par étape : chaque étape est une course E-Ride normale, avec le guide à chaque arrêt.", 'Voir les circuits'],
                'tour, tours, circuit, circuits, tourist, touriste, guide, museum, musée, waterfall, chute'],
            ['account', 'safety',
                ['Let someone follow your ride', 'Add trusted contacts: they can follow your trip live when you share it.', 'Add contacts'],
                ['Laissez un proche suivre votre course', 'Ajoutez des contacts de confiance : ils peuvent suivre votre course en direct quand vous la partagez.', 'Ajouter des contacts'],
                'safety, sécurité, securite, trusted contact, contact de confiance, share trip, partager course, emergency contact'],
            ['account', 'refer',
                ['Invite your friends', 'Share your E-Ride code with friends and family so they can start riding too.', 'Invite friends'],
                ['Invitez vos amis', 'Partagez votre code E-Ride avec vos amis et votre famille pour qu\'ils roulent aussi.', 'Inviter des amis'],
                'refer, referral, parrainage, invite, inviter, code promo ami, friend code'],
            ['account', 'coupons',
                ['Your coupons and offers', 'See the discounts you can use on your next ride.', 'See offers'],
                ['Vos coupons et offres', 'Voyez les réductions à utiliser sur votre prochaine course.', 'Voir les offres'],
                'coupon, coupons, promo, promotion, discount, réduction, reduction, offre, offer'],
        ];
    }
};
