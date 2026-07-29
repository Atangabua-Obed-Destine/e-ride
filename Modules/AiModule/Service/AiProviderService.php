<?php

namespace Modules\AiModule\Service;

use Illuminate\Support\Facades\Cache;
use Modules\AiModule\Exceptions\AiProviderException;
use Modules\AiModule\Exceptions\ValidationException;
use Modules\AiModule\Service\Interfaces\AiProviderServiceInterface;
use Modules\AiModule\Service\Interfaces\AiSettingServiceInterface;
use Modules\AiModule\Service\Interfaces\ClaudeServiceInterface;
use Modules\AiModule\Service\Interfaces\OpenAiServiceInterface;
use Modules\AiModule\Service\Interfaces\ResponseValidatorServiceInterface;

class AiProviderService implements AiProviderServiceInterface
{
    protected array $providers;
    protected $aiSettingService;
    protected $responseValidatorService;

    public function __construct(OpenAiServiceInterface $openAi, ClaudeServiceInterface $claude, AiSettingServiceInterface $aiSettingService, ResponseValidatorServiceInterface $responseValidatorService)
    {
        $this->providers = [$openAi, $claude];
        $this->aiSettingService = $aiSettingService;
        $this->responseValidatorService = $responseValidatorService;
    }

    public function providers(): array
    {
        return $this->providers;
    }

    public function getAvailableProviderObject()
    {
        $activeAiProvider = $this->aiSettingService->getActiveAiProvider();
        foreach ($this->providers as $provider) {
            if ($activeAiProvider->ai_name === $provider->getName()) {
                $provider->setApiKey($activeAiProvider->api_key);
                $provider->setOrganization($activeAiProvider->organization_id);
                return $provider;
            }
        }

        throw new AIProviderException('No matching AI provider found.');
    }

    public function generate(string $prompt, ?string $imageUrl = null, array $options = []): string
    {
        $providerObject = $this->getAvailableProviderObject();
        $appMode = env('APP_MODE');
        $section = $options['section'] ?? '';

        if ($appMode === 'demo') {
            $ip = request()->header('x-forwarded-for');
            $cacheKey = 'demo_ip_usage_' . $ip;
            $count = Cache::get($cacheKey, 0);
            if ($count >= 10) {
                throw new ValidationException("Demo limit reached: You can only generate 10 times.");
            }
            Cache::forever($cacheKey, $count + 1);
        }
        $response = $providerObject->generate($prompt, $imageUrl);

        $validatorMap = [
            'BlogTitle' => 'validateBlogTitle',
            'BlogDescription' => 'validateBlogDescription',
            'BlogSeo' => 'validateBlogSeo',
            'BlogTitleSuggestion' => 'validateBlogTitleSuggestion',
            'BlogTitleFromContents' => 'validateBlogTitleFromContents'
        ];

        if ($section && isset($validatorMap[$section])) {
            $this->responseValidatorService->{$validatorMap[$section]}($response, $options['context'] ?? null);
        }

        return $response;
    }

}
