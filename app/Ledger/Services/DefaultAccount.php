<?php

namespace App\Ledger\Services;

use App\Ledger\Models\ChartOfAccount;
use App\Ledger\Models\DefaultAccountMapping;

/** Small lookups: the account a mapping points at, an account by its code. */
class DefaultAccount
{
    public static function id(string $type, string $key = ''): ?int
    {
        return DefaultAccountMapping::accountFor($type, $key);
    }

    public static function code(string $code): ?int
    {
        return ChartOfAccount::where('account_code', $code)->value('id');
    }
}
