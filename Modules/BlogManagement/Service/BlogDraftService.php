<?php

namespace Modules\BlogManagement\Service;


use App\Service\BaseService;

use Modules\BlogManagement\Repository\BlogDraftRepositoryInterface;
use Modules\BlogManagement\Service\Interfaces\BlogAuthorServiceInterface;
use Modules\BlogManagement\Service\Interfaces\BlogDraftServiceInterface;

class BlogDraftService extends BaseService implements BlogDraftServiceInterface
{
    protected $blogDraftRepository;
    protected BlogAuthorServiceInterface $blogAuthorService;

    public function __construct(BlogDraftRepositoryInterface $blogDraftRepository, BlogAuthorServiceInterface $blogAuthorService)
    {
        parent::__construct($blogDraftRepository);
        $this->blogDraftRepository = $blogDraftRepository;
        $this->blogAuthorService = $blogAuthorService;
    }

    public function saveBlogDraft(array $data): void
    {
        $this->syncAuthor($data);

        $blogDraft = $data['blogDraft'] ?? null;
        $blog = $blogDraft?->blog;
        $isPublished = array_key_exists('is_published', $data);
        $oldDraftThumbnail = $blogDraft?->thumbnail ?? '';
        $oldPublishedThumbnail = $blog?->thumbnail ?? '';

        $this->deleteRemovedSummernoteImages($blogDraft?->description, $data['description'] ?? '');

        if ($isPublished) {
            if (array_key_exists('thumbnail', $data)) {
                $oldFiles = array_values(array_unique(array_filter([$oldDraftThumbnail, $oldPublishedThumbnail])));
                $fileName = fileUploader('blog/', APPLICATION_IMAGE_FORMAT, $data['thumbnail'], $oldFiles);
                $data['thumbnail'] = $fileName;
            } else {
                $data['thumbnail'] = $oldDraftThumbnail;
                if ($oldPublishedThumbnail !== '' && $oldPublishedThumbnail !== $oldDraftThumbnail) {
                    fileRemover('blog/', $oldPublishedThumbnail);
                }
            }

            $data['status'] = 1;
            $blog->update($data);
            $blogDraft->delete();

            return;
        }

        if (array_key_exists('thumbnail', $data)) {
            $oldFile = ($oldDraftThumbnail !== '' && $oldDraftThumbnail !== $oldPublishedThumbnail) ? $oldDraftThumbnail : '';
            $fileName = fileUploader('blog/', APPLICATION_IMAGE_FORMAT, $data['thumbnail'], $oldFile);
            $data['thumbnail'] = $fileName;
        } else {
            $data['thumbnail'] = $oldDraftThumbnail;
        }

        $blogDraft->update($data);
    }

    private function deleteRemovedSummernoteImages(?string $oldDescription, ?string $newDescription): void
    {
        $oldImages = $this->extractSummernoteImageNames($oldDescription);
        $newImages = $this->extractSummernoteImageNames($newDescription);

        foreach (array_diff($oldImages, $newImages) as $image) {
            fileRemover('blog/summernote/', $image);
        }
    }

    private function extractSummernoteImageNames(?string $html): array
    {
        if (empty($html)) {
            return [];
        }

        preg_match_all('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches);

        return collect($matches[1] ?? [])
            ->filter(fn ($src) => str_contains(parse_url($src, PHP_URL_PATH) ?? '', '/blog/summernote/'))
            ->map(fn ($src) => basename(parse_url($src, PHP_URL_PATH)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function syncAuthor(array &$data): void
    {
        if (!array_key_exists('writer', $data)) {
            return;
        }

        $writer = trim((string) $data['writer']);
        $author = $this->blogAuthorService->syncAuthorByName($writer);

        $data['blog_author_id'] = $author?->id;
        unset($data['writer']);
    }
}
