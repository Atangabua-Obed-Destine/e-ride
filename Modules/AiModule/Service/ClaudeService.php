<?php

namespace Modules\AiModule\Service;

use Modules\AiModule\Service\Interfaces\ClaudeServiceInterface;

class ClaudeService implements ClaudeServiceInterface
{
    public function getName(): string
    {
        return 'Claude';
    }

    public function generate(string $prompt, ?string $imageUrl = null, array $options = []): string
    {

    }
}
