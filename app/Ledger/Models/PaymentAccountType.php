<?php

namespace App\Ledger\Models;

use App\Ledger\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

/** A kind of payment account (paxhitestbed PaymentAccountType): cash box, bank account, mobile money. */
class PaymentAccountType extends Model
{
    use BelongsToBusiness;

    const KINDS = ['cash' => '571', 'bank' => '521', 'mobile_money' => '5521'];

    protected $fillable = ['business_id', 'title', 'slug', 'kind', 'description', 'status'];

    protected $casts = ['status' => 'boolean'];

    public function accounts()
    {
        return $this->hasMany(PaymentAccount::class, 'account_type_id');
    }

    /** The three kinds the books start with (made once per set of books). */
    public static function ensureDefaults(): void
    {
        foreach (array_keys(self::KINDS) as $kind) {
            if (! self::where('kind', $kind)->exists()) {
                self::create(['title' => __('ledger_accounts.type_'.$kind, [], 'en'), 'slug' => str_replace('_', '-', $kind), 'kind' => $kind, 'status' => 1]);
            }
        }
    }

    /** The type's name in the screen's language (the default ones are translated). */
    public function label(): string
    {
        $key = 'ledger_accounts.type_'.$this->kind;

        return $this->title === __($key, [], 'en') ? __($key) : $this->title;
    }
}
