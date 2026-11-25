<?php

namespace Modules\BusManagement\Lib;

/**
 * What is withheld from a monthly pay in Cameroon, and what the employer adds.
 *
 * The employee side is the DGI's "Barème indicatif de liquidation de l'IRPP et autres retenues
 * salariales", taken from paxhitestbed's CameroonTaxTable (checked there against 11 972 rows of the
 * printed table: 98% exact, all within one franc):
 *   CNPS  = 4.2% of gross, at most 75 000 a month (gross capped at 1 785 715)
 *   IRPP  = (gross - threshold) x marginal, by band (the statutory 10/15/25/35% on gross x 70%
 *           less CNPS less the 500 000 yearly allowance, folded into five bands)
 *   CAC   = 10% of the IRPP (council surcharge)
 *   CFC   = 1% of gross (housing fund), from 52 000
 *   CRTV  = a flat amount by band of 100 000 (audio-visual tax), from 52 000
 *   TDL   = a flat amount by band (local development tax). Not in the barème: the bands are the ones
 *           paxhitestbed uses, to be confirmed with the accountant.
 * The employer side (CNPS pension, family allowances, work accident, CFC 1.5%, FNE 1%) uses rates
 * the books can change in Settings, because the work-accident rate depends on the company's risk
 * group on its CNPS registration.
 */
class CameroonPayroll
{
    public const FLOOR = 52000;
    public const CNPS_CAP_BASE = 1785715;
    public const CNPS_RATE = 4.2;
    public const CNPS_CAP_AMOUNT = 75000;
    public const CAC_RATE = 10.0;
    public const CFC_RATE = 1.0;
    public const OPEN_TOP = 50000000;

    public const INCOME_TAX = [
        ['min' => 0, 'max' => 316616, 'percent' => 6.58, 'threshold' => 63323.20],
        ['min' => 316617, 'max' => 443262, 'percent' => 9.87, 'threshold' => 147754.14],
        ['min' => 443263, 'max' => 696555, 'percent' => 16.45, 'threshold' => 265957.45],
        ['min' => 696556, 'max' => 1785714, 'percent' => 23.03, 'threshold' => 388985.38],
        ['min' => 1785715, 'max' => self::OPEN_TOP, 'percent' => 24.50, 'threshold' => 472789.12],
    ];

    public const AUDIO_VISUAL = [
        [self::FLOOR, 100000, 750], [100001, 200000, 1950], [200001, 300000, 3250], [300001, 400000, 4550], [400001, 500000, 5850],
        [500001, 600000, 7150], [600001, 700000, 8450], [700001, 800000, 9750], [800001, 900000, 11050], [900001, 1000000, 12350],
        [1000001, self::OPEN_TOP, 13000],
    ];

    public const LOCAL_DEVELOPMENT = [
        [0, 61999, 0], [62000, 75000, 250], [75001, 100000, 500], [100001, 125000, 750], [125001, 150000, 1000], [150001, 200000, 1250],
        [200001, 250000, 1500], [250001, 300000, 2000], [300001, 500000, 2250], [500001, self::OPEN_TOP, 2500],
    ];

    /** Employer rates (percent), changeable per books. */
    public const EMPLOYER_DEFAULTS = ['pension' => 4.2, 'family' => 7.0, 'accident' => 2.5, 'cfc' => 1.5, 'fne' => 1.0];

    /**
     * One month's pay.
     * @return array{gross: float, cnps: float, irpp: float, cac: float, cfc: float, crtv: float, tdl: float, withheld: float, net: float,
     *               employer_cnps: float, employer_taxes: float, employer: array, cost: float}
     */
    public static function compute(float $gross, array $employerRates = []): array
    {
        $gross = round(max(0, $gross));
        $rates = array_merge(self::EMPLOYER_DEFAULTS, array_filter($employerRates, fn($v) => $v !== null && $v !== ''));
        $capped = min($gross, self::CNPS_CAP_BASE);
        $cnps = min(round($capped * self::CNPS_RATE / 100), self::CNPS_CAP_AMOUNT);
        $irpp = 0.0;
        foreach (array_reverse(self::INCOME_TAX) as $band) {
            if ($gross >= $band['min']) {
                $irpp = max(0, round(($gross - $band['threshold']) * $band['percent'] / 100));
                break;
            }
        }
        $cac = round($irpp * self::CAC_RATE / 100);
        $above = $gross >= self::FLOOR;
        $cfc = $above ? round($gross * self::CFC_RATE / 100) : 0.0;
        $crtv = $above ? self::band(self::AUDIO_VISUAL, $gross) : 0.0;
        $tdl = self::band(self::LOCAL_DEVELOPMENT, $gross);
        $withheld = $cnps + $irpp + $cac + $cfc + $crtv + $tdl;
        $employer = [
            'pension' => round($capped * (float)$rates['pension'] / 100),
            'family' => round($capped * (float)$rates['family'] / 100),
            'accident' => round($capped * (float)$rates['accident'] / 100),
            'cfc' => round($gross * (float)$rates['cfc'] / 100),
            'fne' => round($gross * (float)$rates['fne'] / 100),
        ];
        $employerCnps = $employer['pension'] + $employer['family'] + $employer['accident'];
        $employerTaxes = $employer['cfc'] + $employer['fne'];
        return [
            'gross' => (float)$gross, 'cnps' => (float)$cnps, 'irpp' => (float)$irpp, 'cac' => (float)$cac, 'cfc' => (float)$cfc,
            'crtv' => (float)$crtv, 'tdl' => (float)$tdl, 'withheld' => (float)$withheld, 'net' => (float)($gross - $withheld),
            'employer_cnps' => (float)$employerCnps, 'employer_taxes' => (float)$employerTaxes, 'employer' => $employer,
            'cost' => (float)($gross + $employerCnps + $employerTaxes),
        ];
    }

    protected static function band(array $table, float $gross): float
    {
        foreach ($table as [$min, $max, $amount]) {
            if ($gross >= $min && $gross <= $max) {
                return (float)$amount;
            }
        }
        return 0.0;
    }

    /** Points taken off the printed barème (gross => IRPP), for the tests. */
    public static function checkpoints(): array
    {
        return [63250 => 0, 63500 => 12, 90000 => 1755, 100000 => 2413, 110000 => 3071, 120000 => 3729, 170000 => 7019, 180000 => 7677,
            316500 => 16659, 316750 => 16680, 443250 => 29165, 500000 => 38500, 696500 => 70824, 1000000 => 140717, 1785750 => 321675, 3000000 => 619167];
    }
}
