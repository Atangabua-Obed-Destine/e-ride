<?php

namespace Modules\AiModule\Service\Interfaces;

interface ResponseValidatorServiceInterface
{
    public function validateBlogTitle(string $response, ?string $context = null): void;
    public function validateBlogDescription(string $response, ?string $context = null): void;
    public function validateBlogSeo(string $response, ?string $context = null): void;
    public function validateBlogTitleSuggestion(string $response, ?string $context = null): void;
    public function validateBlogTitleFromContents(string $response): void;
}
