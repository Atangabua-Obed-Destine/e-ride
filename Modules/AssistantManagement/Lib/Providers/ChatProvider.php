<?php

namespace Modules\AssistantManagement\Lib\Providers;

/** One AI service Ask E-Ride can ask. Returns the model's text (JSON asked for in the prompt). */
interface ChatProvider
{
    /**
     * @throws ProviderUnavailable when it can't answer now (quota, credits, outage, bad key)
     */
    public function complete(string $system, string $user, string $key, string $model): string;
}
