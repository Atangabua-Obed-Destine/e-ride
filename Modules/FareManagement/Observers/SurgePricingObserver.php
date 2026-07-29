<?php

namespace Modules\FareManagement\Observers;

use Modules\FareManagement\Entities\SurgePricing;

class SurgePricingObserver
{
    public function creating(SurgePricing $surgePricing): void
    {
        $latestId = SurgePricing::whereNotNull('readable_id')->lockForUpdate()->max('readable_id');
        $surgePricing->readable_id =( $latestId ?? 9999) + 1;
    }
}
