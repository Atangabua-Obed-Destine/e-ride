<?php

namespace Modules\BusinessManagement\Observers;

use Illuminate\Support\Facades\Cache;
use Modules\BusinessManagement\Entities\BusinessSetting;

class BusinessSettingObserver
{
    /**
     * Handle the BusinessSetting "created" event.
     */
    public function created(BusinessSetting $businesssetting): void
    {
        $this->removeCache();
    }

    /**
     * Handle the BusinessSetting "updated" event.
     */
    public function updated(BusinessSetting $businesssetting): void
    {
        $this->removeCache();
    }

    /**
     * Handle the BusinessSetting "deleted" event.
     */
    public function deleted(BusinessSetting $businesssetting): void
    {
        $this->removeCache();
    }

    /**
     * Handle the BusinessSetting "restored" event.
     */
    public function restored(BusinessSetting $businesssetting): void
    {
        $this->removeCache();
    }

    /**
     * Handle the BusinessSetting "force deleted" event.
     */
    public function forceDeleted(BusinessSetting $businesssetting): void
    {
        $this->removeCache();
    }

    private function removeCache()
    {
        Cache::forget(CACHE_BUSINESS_SETTINGS);
    }
}
