<?php

namespace Modules\AiModule\Service;

use Modules\AiModule\Service\Interfaces\AiProviderServiceInterface;
use Modules\AiModule\Service\Interfaces\BlogDescriptionPromptServiceInterface;
use Modules\AiModule\Service\Interfaces\BlogSeoPromptServiceInterface;
use Modules\AiModule\Service\Interfaces\BlogTitleFromContentsPromptServiceInterface;
use Modules\AiModule\Service\Interfaces\BlogTitlePromptServiceInterface;
use Modules\AiModule\Service\Interfaces\BlogTitleSuggestionPromptServiceInterface;
use Modules\AiModule\Service\Interfaces\ContentGeneratorServiceInterface;


class ContentGeneratorService implements ContentGeneratorServiceInterface
{
    protected array $templates = [];
    protected $aiProvider;
    public function __construct(AiProviderServiceInterface $aiProvider)
    {
        $this->loadTemplates();
        $this->aiProvider = $aiProvider;
    }

    protected function loadTemplates(): void
    {
        $templateClasses = [
            'BlogTitle' => BlogTitlePromptServiceInterface::class,
            'BlogDescription' => BlogDescriptionPromptServiceInterface::class,
            'BlogSeo' => BlogSeoPromptServiceInterface::class,
            'BlogTitleSuggestion' => BlogTitleSuggestionPromptServiceInterface::class,
            'BlogTitleFromContents' => BlogTitleFromContentsPromptServiceInterface::class
        ];
        foreach ($templateClasses as $type => $interface) {
            if (interface_exists($interface)) {
                $this->templates[$type] = app($interface);
            }
        }
    }

    public function generateContent(string $contentType, mixed $context = null, string $langCode = 'en', ?string $description = null, ?string $imageUrl = null): string
    {
        $template = $this->templates[$contentType];
        $prompt = $template->build(context: $context, langCode: $langCode, description: $description);

        return $this->aiProvider->generate(prompt: $prompt, imageUrl: $imageUrl, options: ['section' => $contentType, 'context' => $context, 'description' => $description]);
    }
}
