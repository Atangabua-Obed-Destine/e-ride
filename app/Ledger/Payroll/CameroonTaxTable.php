<?php

namespace App\Ledger\Payroll;

/**
 * paxhitestbed's App\Support\Tax\CameroonTaxTable, as it is: the payroll taxes a Cameroonian
 * employer withholds, as one table read by the installer (TaxDefaults).
 *
 * The DGI's "Barème indicatif de liquidation de l'IRPP et autres retenues salariales" is the
 * statutory computation rendered row by row:
 *
 *      CNPS        = min(gross x 4.2%, 75 000)        employee share, capped
 *      net taxable = gross x 70% - CNPS - 500 000/12  30% allowance, abatement
 *      IRPP        = 10 / 15 / 25 / 35% on the annualised net, by band
 *
 * Below the CNPS cap, within a band, IRPP = (gross - threshold) x marginal, each marginal a
 * statutory rate times 0.658 (0.70 past the cap): one tax_settings row each, as percentange with
 * max_no_taxable_amount. paxhitestbed checked the five bands against 11 972 rows parsed out of the
 * PDF: 98.18% exact, 100% within one franc (the printed table's own rounding).
 */
class CameroonTaxTable
{
    /** The lowest gross pay the barème covers (its first row already carries CFC and CRTV). */
    public const FLOOR = 52000;

    public const CNPS_CAP_BASE = 1785715;
    public const CNPS_CAP_AMOUNT = 75000;
    public const CNPS_RATE = 4.2;

    public const CFC_EMPLOYEE_RATE = 1.0;
    public const CFC_EMPLOYER_RATE = 1.5;
    public const FNE_EMPLOYER_RATE = 1.0;

    /** The council surcharge (CAC), as a percentage of the income tax it follows. */
    public const CAC_RATE = 10.0;

    public const OPEN_TOP = 50000000;

    public const INCOME_TAX = [
        ['order' => 1, 'min' => 0,       'max' => 316616,   'percent' => 6.58,  'threshold' => 63323.20,  'note' => '10% statutory x 0.658'],
        ['order' => 2, 'min' => 316617,  'max' => 443262,   'percent' => 9.87,  'threshold' => 147754.14, 'note' => '15% statutory x 0.658'],
        ['order' => 3, 'min' => 443263,  'max' => 696555,   'percent' => 16.45, 'threshold' => 265957.45, 'note' => '25% statutory x 0.658'],
        ['order' => 4, 'min' => 696556,  'max' => 1785714,  'percent' => 23.03, 'threshold' => 388985.38, 'note' => '35% statutory x 0.658'],
        ['order' => 5, 'min' => 1785715, 'max' => self::OPEN_TOP, 'percent' => 24.50, 'threshold' => 472789.12, 'note' => '35% statutory x 0.70, past the CNPS cap'],
    ];

    /** Audio-visual tax (CRTV): a flat amount per 100 000 of pay, off the barème's CRTV column. */
    public const AUDIO_VISUAL = [
        ['order' => 1,  'min' => self::FLOOR, 'max' => 100000,  'amount' => 750],
        ['order' => 2,  'min' => 100250,      'max' => 200000,  'amount' => 1950],
        ['order' => 3,  'min' => 200250,      'max' => 300000,  'amount' => 3250],
        ['order' => 4,  'min' => 300250,      'max' => 400000,  'amount' => 4550],
        ['order' => 5,  'min' => 400250,      'max' => 500000,  'amount' => 5850],
        ['order' => 6,  'min' => 500250,      'max' => 600000,  'amount' => 7150],
        ['order' => 7,  'min' => 600250,      'max' => 700000,  'amount' => 8450],
        ['order' => 8,  'min' => 700250,      'max' => 800000,  'amount' => 9750],
        ['order' => 9,  'min' => 800250,      'max' => 900000,  'amount' => 11050],
        ['order' => 10, 'min' => 900250,      'max' => 1000000, 'amount' => 12350],
        ['order' => 11, 'min' => 1000250,     'max' => self::OPEN_TOP, 'amount' => 13000],
    ];

    /**
     * Local development tax (TDL): not on the barème ("hors taxe de développement local"). These
     * are the bands paxhitestbed uses; confirm them with the accountant.
     */
    public const LOCAL_DEVELOPMENT = [
        ['order' => 1,  'min' => 0,      'max' => 60000,   'amount' => 0],
        ['order' => 2,  'min' => 62000,  'max' => 75000,   'amount' => 250],
        ['order' => 3,  'min' => 75001,  'max' => 100000,  'amount' => 500],
        ['order' => 4,  'min' => 100001, 'max' => 125000,  'amount' => 750],
        ['order' => 5,  'min' => 125001, 'max' => 150000,  'amount' => 1000],
        ['order' => 6,  'min' => 150001, 'max' => 200000,  'amount' => 1250],
        ['order' => 7,  'min' => 200001, 'max' => 250000,  'amount' => 1500],
        ['order' => 8,  'min' => 250001, 'max' => 300000,  'amount' => 2000],
        ['order' => 9,  'min' => 300001, 'max' => 500000,  'amount' => 2250],
        ['order' => 10, 'min' => 500001, 'max' => self::OPEN_TOP, 'amount' => 2500],
    ];

    /** The group codes this table configures (the code is the stable key). */
    public const GROUPS = [
        'PIT' => 'Personal Income Tax',
        'LDT' => 'Local Development Tax',
        'HLF' => 'Housing Loan Fund',
        'CRTV' => 'Audio Visual Tax',
        'IA' => 'Industrial Accident',
    ];

    /** Points off the printed barème: gross monthly pay => income tax due. */
    public static function checkpoints(): array
    {
        return [
            63250 => 0, 63500 => 12, 90000 => 1755, 100000 => 2413, 110000 => 3071, 120000 => 3729, 170000 => 7019,
            180000 => 7677, 316500 => 16659, 316750 => 16680, 443250 => 29165, 500000 => 38500, 696500 => 70824,
            1000000 => 140717, 1785750 => 321675, 3000000 => 619167,
        ];
    }
}
