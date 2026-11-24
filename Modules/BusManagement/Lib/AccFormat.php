<?php

namespace Modules\BusManagement\Lib;

/** Figures in the accounts pages: whole FCFA, spaces between thousands. */
class AccFormat
{
    public static function n($v, bool $blankZero = false): string
    {
        $v = round((float)$v);
        if ($blankZero && $v == 0) {
            return '';
        }
        return ($v < 0 ? '-' : '') . number_format(abs($v), 0, '.', ' ');
    }

    public static function cls($v): string
    {
        return round((float)$v) < 0 ? 'neg' : '';
    }
}
