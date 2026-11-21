<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Knowledge for Ask E-Ride, and the info sheet on Trip Setup: how to help the
 * driver find you (a written description and a voice note). Also a "Did you
 * know?" card for it.
 */
return new class extends Migration {
    private const KEY = 'pickup_help';

    public function up(): void
    {
        if (!Schema::hasTable('assistant_entries') || DB::table('assistant_entries')->where('help_key', self::KEY)->exists()) {
            return;
        }
        $now = now();
        DB::table('assistant_entries')->insert([
            'id' => (string)Str::uuid(), 'help_key' => self::KEY, 'is_active' => true,
            'sort' => (int)DB::table('assistant_entries')->max('sort') + 1,
            'service' => 'rides', 'tip_action' => 'ride',
            'title_en' => 'Help your driver find you',
            'body_en' => "When you book a ride or send a parcel, say where the driver should find you: the gate, the shop opposite, the junction.\n"
                . "Write it, or record a voice note in Pidgin, English or French. Name the places people know.\n"
                . "The driver hears your voice note when your request rings and on the way to you, with the words written under it.\n"
                . "If you also say where you are going, E-Ride fills in the destination for you. You check it before you book.",
            'tip_label_en' => 'Book a ride',
            'title_fr' => 'Aidez votre chauffeur à vous trouver',
            'body_fr' => "Quand vous réservez une course ou envoyez un colis, dites où le chauffeur doit vous trouver : le portail, la boutique en face, le carrefour.\n"
                . "Écrivez-le, ou enregistrez un message vocal en pidgin, en anglais ou en français. Citez les lieux que tout le monde connaît.\n"
                . "Le chauffeur écoute votre message quand votre demande sonne et en venant vers vous, avec le texte écrit dessous.\n"
                . "Si vous dites aussi où vous allez, E-Ride remplit la destination pour vous. Vous vérifiez avant de réserver.",
            'tip_label_fr' => 'Réserver une course',
            'search_words' => 'voice note, message vocal, vocal, pickup note, note de prise en charge, driver can not find me, chauffeur ne me trouve pas, directions, indications, describe place, décrire',
            'created_at' => $now, 'updated_at' => $now,
        ] + (Schema::hasColumn('assistant_entries', 'audience') ? ['audience' => 'customer'] : []));
    }

    public function down(): void
    {
        DB::table('assistant_entries')->where('help_key', self::KEY)->delete();
    }
};
