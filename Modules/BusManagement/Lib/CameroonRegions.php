<?php

namespace Modules\BusManagement\Lib;

use Illuminate\Support\Str;

/** Cameroon's 10 regions and the towns bus agencies leave from, for head office reports by region. */
class CameroonRegions
{
    public const REGIONS = [
        'adamawa' => 'Adamawa',
        'centre' => 'Centre',
        'east' => 'East',
        'far_north' => 'Far North',
        'littoral' => 'Littoral',
        'north' => 'North',
        'northwest' => 'Northwest',
        'south' => 'South',
        'southwest' => 'Southwest',
        'west' => 'West',
    ];

    protected const TOWNS = [
        'northwest' => ['bamenda', 'kumbo', 'wum', 'ndop', 'nkambe', 'mbengwi', 'bafut', 'bali', 'fundong', 'santa', 'batibo', 'bambui'],
        'southwest' => ['buea', 'limbe', 'kumba', 'mamfe', 'tiko', 'mutengene', 'muyuka', 'idenau', 'mundemba', 'fontem', 'menji'],
        'littoral' => ['douala', 'nkongsamba', 'edea', 'loum', 'manjo', 'mbanga', 'yabassi', 'dibombari', 'penja'],
        'centre' => ['yaounde', 'mbalmayo', 'obala', 'bafia', 'eseka', 'nanga eboko', 'mfou', 'akonolinga', 'soa', 'ntui'],
        'west' => ['bafoussam', 'dschang', 'mbouda', 'bangangte', 'foumban', 'bafang', 'baham', 'bandjoun', 'foumbot', 'bazou'],
        'adamawa' => ['ngaoundere', 'meiganga', 'tibati', 'banyo', 'tignere'],
        'north' => ['garoua', 'guider', 'poli', 'tchollire', 'pitoa'],
        'far_north' => ['maroua', 'kousseri', 'mokolo', 'yagoua', 'mora', 'kaele'],
        'east' => ['bertoua', 'batouri', 'abong-mbang', 'abong mbang', 'yokadouma', 'belabo'],
        'south' => ['ebolowa', 'kribi', 'sangmelima', 'ambam', 'kye-ossi', 'campo', 'lolodorf'],
    ];

    /** The region key of a town, or null when unknown (the admin sets it). */
    public static function of(?string $city): ?string
    {
        $c = trim(Str::lower(Str::ascii((string)$city)));
        if ($c === '') {
            return null;
        }
        foreach (self::TOWNS as $region => $towns) {
            foreach ($towns as $town) {
                if ($c === $town || str_starts_with($c, $town . ' ') || str_contains($c, $town)) {
                    return $region;
                }
            }
        }
        return null;
    }

    public static function label(?string $region): string
    {
        return $region && isset(self::REGIONS[$region]) ? translate(self::REGIONS[$region]) : translate('Other');
    }
}
