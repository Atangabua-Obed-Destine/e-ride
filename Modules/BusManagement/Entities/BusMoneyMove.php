<?php

namespace Modules\BusManagement\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * Money moving on a booking, written when it happens (append only): a ticket paid, money paid
 * beyond the fare, a refund owed, a refund given back at the counter, a refund sent, a paid
 * seat moved to another trip. The ledger posts each one once.
 */
class BusMoneyMove extends Model
{
    public const SALE = 'sale';
    public const EXTRA = 'extra';
    public const REFUND_OWED = 'refund_owed';
    public const CASH_REFUND = 'cash_refund';
    public const REFUND_SENT = 'refund_sent';
    public const REBOOK = 'rebook';

    public const UPDATED_AT = null;

    protected $table = 'bus_money_moves';

    protected $fillable = ['agency_id', 'booking_id', 'departure_id', 'from_departure_id', 'kind', 'channel', 'method', 'amount', 'staff_user_id', 'at', 'entry_id', 'skipped', 'backfilled'];

    protected $casts = ['at' => 'datetime', 'amount' => 'float', 'backfilled' => 'boolean'];
}
