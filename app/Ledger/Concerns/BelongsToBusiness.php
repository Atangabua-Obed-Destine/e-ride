<?php

namespace App\Ledger\Concerns;

use App\Ledger\Ledger;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every ledger row belongs to one business: queries only see the current
 * business's rows and new rows are stamped with it (see App\Ledger\Ledger).
 */
trait BelongsToBusiness
{
    protected static function bootBelongsToBusiness()
    {
        static::addGlobalScope('business', function (Builder $q) {
            $q->where($q->getModel()->getTable().'.business_id', Ledger::businessId());
        });

        static::creating(function ($model) {
            if (empty($model->business_id)) {
                $model->business_id = Ledger::businessId();
            }
        });
    }
}
