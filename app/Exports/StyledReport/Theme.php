<?php

namespace App\Exports\StyledReport;

use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

final class Theme
{
    public const HEADER_FILL = '005D5F';
    public const HEADER_TEXT = 'FFFFFF';
    public const TITLE_TEXT  = '000000';
    public const META_LABEL_FILL = 'F2F2F2';
    public const BORDER      = '000000';

    public const TITLE_PT     = 22;
    public const META_PT      = 11;
    public const HEADER_PT    = 11;
    public const BODY_PT      = 11;

    public const TITLE_HEIGHT  = 50;
    public const META_HEIGHT   = 36;
    public const HEADER_HEIGHT = 28;
    public const BODY_HEIGHT   = 20;

    public static function font(): string
    {
        try {
            $configured = businessConfig('export_font', 'business_information')?->value;
            if (is_string($configured) && $configured !== '') {
                return $configured;
            }
        } catch (\Throwable) {
            // business config table may not be available yet (e.g. during install) — fall through
        }
        return config('export.font', 'Calibri');
    }

    public static function headerFill(): string
    {
        try {
            $websiteColor = businessConfig('website_color')?->value;
            $primary = is_array($websiteColor) ? ($websiteColor['primary'] ?? null) : null;
            if (is_string($primary)) {
                $primary = ltrim(trim($primary), '#');
                if (preg_match('/^[A-Fa-f0-9]{6}$/', $primary)) {
                    return strtoupper($primary);
                }
            }
        } catch (\Throwable) {
            // business settings table may not be available yet — fall through
        }

        return self::HEADER_FILL;
    }

    public static function currencyFormatCode(): string
    {
        $symbol   = self::currencySymbol();
        $points   = self::currencyDecimalPoints();
        $position = self::currencySymbolPosition();
        $decimals = $points > 0 ? '.' . str_repeat('0', $points) : '';
        $escaped  = '"' . str_replace('"', '""', $symbol) . '"';
        return $position === 'left'
            ? $escaped . ' #,##0' . $decimals . ';' . $escaped . ' -#,##0' . $decimals
            : '#,##0' . $decimals . ' ' . $escaped . ';-#,##0' . $decimals . ' ' . $escaped;
    }

    public static function dateFormatCode(): string
    {
        return 'dd/mm/yyyy';
    }

    public static function dateTimeFormatCode(): string
    {
        return 'dd/mm/yyyy hh:mm';
    }

    public static function formatCodeFor(string $columnFormat): ?string
    {
        return match ($columnFormat) {
            ColumnFormat::CURRENCY   => self::currencyFormatCode(),
            ColumnFormat::DATE       => self::dateFormatCode(),
            ColumnFormat::DATETIME   => self::dateTimeFormatCode(),
            ColumnFormat::INTEGER    => '#,##0',
            ColumnFormat::DECIMAL    => '#,##0.00',
            ColumnFormat::PERCENTAGE => '0.00%',
            default                  => null,
        };
    }

    public static function alignmentFor(string $columnFormat): string
    {
        return match ($columnFormat) {
            ColumnFormat::CURRENCY,
            ColumnFormat::DECIMAL,
            ColumnFormat::INTEGER,
            ColumnFormat::PERCENTAGE => 'right',
            ColumnFormat::DATE,
            ColumnFormat::DATETIME,
            ColumnFormat::STATUS     => 'center',
            default                  => 'left',
        };
    }

    private static function currencySymbol(): string
    {
        try {
            $sym = getSession('currency_symbol');
            if (is_string($sym) && $sym !== '') {
                return $sym;
            }
            $cfg = businessConfig('currency_symbol', 'business_information')?->value;
            if (is_string($cfg) && $cfg !== '') {
                return $cfg;
            }
        } catch (\Throwable) {
            // fall through to default
        }
        return '$';
    }

    private static function currencyDecimalPoints(): int
    {
        try {
            $points = getSession('currency_decimal_point');
            if (is_numeric($points)) {
                return (int)$points;
            }
        } catch (\Throwable) {
        }
        return 0;
    }

    private static function currencySymbolPosition(): string
    {
        try {
            $pos = getSession('currency_symbol_position');
            if (is_string($pos) && in_array($pos, ['left', 'right'], true)) {
                return $pos;
            }
        } catch (\Throwable) {
        }
        return 'left';
    }
}
