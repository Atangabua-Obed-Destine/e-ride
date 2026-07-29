<?php

namespace Modules\AiModule\Service;

use Illuminate\Support\Facades\Log;
use Modules\AiModule\Exceptions\ValidationException;
use Modules\AiModule\Exceptions\ImageValidationException;
use Modules\AiModule\Service\Interfaces\ResponseValidatorServiceInterface;

class ResponseValidatorService implements ResponseValidatorServiceInterface
{
    /**
     * @throws ValidationException
     */
    public function validateBlogTitle(string $response, ?string $context = null): void
    {
        if ($this->isInvalidBlogTitle($response, $context)) {
            throw new ValidationException('The provided input is not valid for generating a blog title. Please provide a meaningful blog title.');
        }
    }

    /**
     * @throws ValidationException
     */
    public function validateBlogDescription(string $response, ?string $context = null): void
    {
        if ($this->isInvalidBlogTitle($response, $context)) {
            throw new ValidationException('The provided input is not valid for generating a blog description. Please provide a meaningful blog title.');
        }
    }

    /**
     * @throws ValidationException
     */
    public function validateBlogSeo(string $response, ?string $context = null): void
    {
        if ($this->isInvalidBlogTitle($response, $context)) {
            throw new ValidationException('The provided input is not valid for generating a blog description. Please provide a meaningful blog title.');
        }
    }

    /**
     * @throws ValidationException
     */
    public function validateBlogTitleSuggestion(string $response, ?string $context = null): void
    {
        if ($this->isInvalidBlogTitle($response, $context)) {
            throw new ValidationException('The provided input is not valid for generating a blog title. Please provide meaningful blog keywords.');
        }
    }

    /**
     * @throws ImageValidationException
     */
    public function validateBlogTitleFromContents(string $response): void
    {
        if ($this->isInvalidImageResponse($response)) {
            throw new ImageValidationException('The uploaded image is not valid for generating Blog content. Please provide a meaningful image.');
        }
    }

    private function isInvalidBlogTitle(string $response, ?string $context = null): bool
    {
        return $this->phraseCheck($response, $context);
    }

    private function isInvalidBlogDescription(string $response, ?string $context = null): bool
    {
        return $this->phraseCheck($response, $context);
    }

    private function isInvalidBlogKeyword(string $response, ?string $context = null): bool
    {
        return $this->phraseCheck($response, $context);
    }

    private function isInvalidImageResponse(string $response): bool
    {
        return $this->phraseCheck($response, null);
    }

    protected function phraseCheck(string $response, ?string $context): bool
    {
        $invalidPhrases = [
            'INVALID_INPUT',
        ];
        foreach ($invalidPhrases as $phrase) {
            if (stripos($response, $phrase) !== false) {
                Log::warning('Invalid phrase detected', ['phrase' => $phrase, 'response' => $response, 'context' => $context]);
                return true;
            }
        }
        return false;
    }
}
