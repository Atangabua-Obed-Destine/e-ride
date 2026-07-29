<?php

namespace Modules\AiModule\Service\Interfaces;

interface PromptServiceInterface
{
    public function build(?string $context = null, ?string $langCode = null, ?string $description = null): string;

    public function getType(): string;
}
