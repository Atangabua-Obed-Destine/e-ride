<?php

namespace Modules\MarketManagement\Lib;

/**
 * E-Ride Market switches and numbers (admin E-Ride Market > Settings), with defaults.
 * Off until an admin turns it on.
 */
class MarketSettings
{
    public const TYPE = 'market_settings';

    public const CATEGORIES = ['fashion', 'phones', 'electronics', 'home', 'food', 'beauty', 'kids', 'vehicle_parts', 'services', 'digital', 'other'];
    public const DIGITAL_KINDS = ['ebook', 'course', 'design', 'music', 'service', 'other'];
    public const DELIVERY_PAYERS = ['buyer', 'seller', 'seller_chooses'];

    /**
     * Words and phrases that stop a post (checked without accents or case, as whole words).
     * Weapons, drugs and medicines, adult content, money schemes, exam leaks, hacked or pirated goods.
     */
    public const DEFAULT_BANNED = "gun\npistol\nrifle\nammunition\nfusil\nmunition\ncannabis\nweed\nmarijuana\ncocaine\nheroin\ntramadol\ncodeine\nkush\ndrogue\nviagra\nantibiotic\nantibiotique\nmedicine\nmedicament\nporn\nporno\nescort\nsex\nsexe\ninvestment plan\ninvestissement garanti\ndouble your money\ndoubler votre argent\nforex signal\nbetting tips\npronostic\nfixed match\nmatch truque\nexam leak\nfuite d examen\nleaked\nsujets bac\nhacked\npirate\ncracked\nfake money\nfaux billets\nvisa offer\njob offer fee\nrecrutement payant";

    public static function all(): array
    {
        $get = fn(string $key, $default) => businessConfig($key, self::TYPE)?->value ?? $default;
        $payer = (string)$get('market_delivery_payer', 'buyer');
        return [
            'enabled' => (bool)(int)$get('market_enabled', 0),
            'digital_allowed' => (bool)(int)$get('market_digital_allowed', 1),
            // A red warning on digital products of shops that are new and not verified.
            'digital_warning' => (bool)(int)$get('market_digital_warning', 1),
            // Distinct people reporting an item before it is hidden until an admin decides.
            'hide_after_reports' => min(20, max(1, (int)$get('market_hide_after_reports', 3))),
            'items_per_shop' => min(500, max(1, (int)$get('market_items_per_shop', 50))),
            'banned_words' => (string)$get('market_banned_words', self::DEFAULT_BANNED),
            // Who pays the rest of a delivery fare when the seller books it (the seller always pays the deposit).
            'delivery_payer' => in_array($payer, self::DELIVERY_PAYERS, true) ? $payer : 'buyer',
        ];
    }

    public static function enabled(): bool
    {
        return self::all()['enabled'];
    }

    /** @return string[] lower case, accents removed, one per line, empty lines dropped */
    public static function bannedList(): array
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', self::all()['banned_words']) as $line) {
            $line = self::fold($line);
            if ($line !== '') {
                $out[] = $line;
            }
        }
        return array_values(array_unique($out));
    }

    /** Lower case, accents removed, punctuation and spaces collapsed to one space: how text is compared with the banned list. */
    public static function fold(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = strtr($text, ['à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u', 'ÿ' => 'y', 'ñ' => 'n']);
        // Punctuation counts as a space: "d'examen" matches "d examen".
        return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text));
    }
}
