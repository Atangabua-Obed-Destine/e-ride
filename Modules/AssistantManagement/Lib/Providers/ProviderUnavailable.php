<?php

namespace Modules\AssistantManagement\Lib\Providers;

use RuntimeException;

/** A provider can't answer now: skip it for $retryAfter seconds and try the next one. */
class ProviderUnavailable extends RuntimeException
{
    public function __construct(string $message, public readonly int $retryAfter = 120)
    {
        parent::__construct($message);
    }

    /** Free quotas and credits come back the next day. */
    public static function untilTomorrow(string $message): self
    {
        return new self($message, max(600, now()->diffInSeconds(now()->addDay()->startOfDay(), false)));
    }
}
