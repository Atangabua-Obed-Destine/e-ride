<?php

namespace Modules\AssistantManagement\Lib\Providers;

use RuntimeException;

/** A provider can't answer now: skip it for $retryAfter seconds and try the next one. */
class ProviderUnavailable extends RuntimeException
{
    /** Overloaded or slow rather than refused: another model of the same provider may answer. */
    public bool $busy = false;

    public function __construct(string $message, public readonly int $retryAfter = 120)
    {
        parent::__construct($message);
    }

    /** Free quotas and credits come back the next day. */
    public static function untilTomorrow(string $message): self
    {
        return new self($message, (int)max(600, now()->diffInSeconds(now()->addDay()->startOfDay(), false)));
    }
}
