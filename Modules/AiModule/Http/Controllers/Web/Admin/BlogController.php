<?php

namespace Modules\AiModule\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use Exception as ExceptionAlias;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AiModule\Service\Interfaces\ContentGeneratorServiceInterface;

class BlogController extends Controller
{
    protected $contentGeneratorService;

    public function __construct(ContentGeneratorServiceInterface $contentGeneratorService)
    {
        $this->contentGeneratorService = $contentGeneratorService;
    }
    public function generateTitle(Request $request): JsonResponse
    {
        try {
            $result = $this->contentGeneratorService->generateContent(contentType: "BlogTitle", context: $request['name']);
            return successResponse(data: $result);
        } catch (ExceptionAlias $exception)
        {
            $status = is_numeric($exception->getCode()) && $exception->getCode() > 0 ? $exception->getCode() : 500;

            return errorResponse(message: $exception->getMessage(), status: $status);
        }
    }

    public function generateDescription(Request $request)
    {
        try {
            $result = $this->contentGeneratorService->generateContent(contentType: "BlogDescription", context: $request['name']);
            $cleanHtml = preg_replace('/<\/?(html|head|body|article)[^>]*>/', '', $result);
            $cleanHtml = preg_replace('/^```(?:html)?|```$/m', '', $cleanHtml);
            $cleanHtml = trim($cleanHtml);

            return successResponse(data: $cleanHtml);
        } catch (ExceptionAlias $exception)
        {
            $status = is_numeric($exception->getCode()) && $exception->getCode() > 0 ? $exception->getCode() : 500;

            return errorResponse(message: $exception->getMessage(), status: $status);
        }
    }

    public function generateSeo(Request $request): JsonResponse
    {
        try {
            $result = $this->contentGeneratorService->generateContent(contentType: "BlogSeo", context: $request['name'], description: $request['description']);
            $result = trim($result);
            $result = preg_replace('/^```(?:json)?\s*/i', '', $result);
            $result = preg_replace('/```$/', '', $result);
            $result = trim($result);
            $data = json_decode($result, true);

            return successResponse(data: $data);
        } catch (ExceptionAlias $exception)
        {
            $status = is_numeric($exception->getCode()) && $exception->getCode() > 0 ? $exception->getCode() : 500;

            return errorResponse(message: $exception->getMessage(), status: $status);
        }
    }

    public function generateTitleSuggestion(Request $request): JsonResponse
    {
        try {

            $result = $this->contentGeneratorService->generateContent(contentType: "BlogTitleSuggestion", context: $request['keywords']);
            $result = trim($result);
            $result = preg_replace('/^```(?:json)?\s*/i', '', $result);
            $result = preg_replace('/```$/', '', $result);
            $result = trim($result);
            $data = json_decode($result, true);

            return successResponse(data: $data);
        } catch (\Exception $e) {
            $status = $e->getCode() > 0 ? $e->getCode() : 500;

            return errorResponse(message: $e->getMessage(), status: $status);
        }
    }

    public function generateTitleFromContents(Request $request): JsonResponse
    {
        try {
            $imageFile = $request->file('image');
            $fileName = fileUploader('blog/ai-image/', image: $imageFile);
            $image = aiImageFullPath($fileName);
            $result = $this->contentGeneratorService->generateContent(contentType: "BlogTitleFromContents", description:$request['description'] , imageUrl: $image['image_full_path']);
            fileRemover('blog/ai-image/', $fileName);
            return successResponse(data: $result, status: 200);
        } catch (\Exception $e) {
            $status = $e->getCode() > 0 ? $e->getCode() : 500;
            return errorResponse(message: $e->getMessage(), status: $status);
        }
    }
}
