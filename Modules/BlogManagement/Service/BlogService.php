<?php

namespace Modules\BlogManagement\Service;


use App\Service\BaseService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\BlogManagement\Repository\BlogDraftRepositoryInterface;
use Modules\BlogManagement\Repository\BlogRepositoryInterface;
use Modules\BlogManagement\Service\Interfaces\BlogAuthorServiceInterface;
use Modules\BlogManagement\Service\Interfaces\BlogServiceInterface;

class BlogService extends BaseService implements BlogServiceInterface
{
    protected $blogRepository;
    protected $blogDraftRepository;
    protected BlogAuthorServiceInterface $blogAuthorService;

    public function __construct(BlogRepositoryInterface $blogRepository, BlogDraftRepositoryInterface $blogDraftRepository, BlogAuthorServiceInterface $blogAuthorService)
    {
        parent::__construct($blogRepository);
        $this->blogRepository = $blogRepository;
        $this->blogDraftRepository = $blogDraftRepository;
        $this->blogAuthorService = $blogAuthorService;
    }

    public function index(array $criteria = [], array $relations = [], array $whereHasRelations = [], array $orderBy = [], ?int $limit = null, ?int $offset = null, array $withCountQuery = [], array $appends = [], array $groupBy = []): Collection|LengthAwarePaginator
    {
        $data = [];
        $searchData = [];

        if (array_key_exists('search', $criteria) && $criteria['search'] != '') {
            $searchData['fields'] = ['title'];
            $searchData['relations'] = [
                'category' => ['name'],
            ];
            $searchData['value'] = $criteria['search'];
        }

        $whereBetweenCriteria = [];
        if (array_key_exists('filter_date', $criteria)) {
            $whereBetweenCriteria = ['published_at' => $criteria['filter_date']];
        }

        if (array_key_exists('blog_category_id', $criteria) && !empty($criteria['blog_category_id']))
        {
            $data = ['blog_category_id' => $criteria['blog_category_id']];
        }

        return $this->blogRepository->getBy(criteria: $data, searchCriteria: $searchData, whereInCriteria: $whereInCriteria ?? [], whereBetweenCriteria: $whereBetweenCriteria, whereHasRelations: $whereHasRelations, relations: $relations, orderBy: $orderBy, limit: $limit, offset: $offset, withCountQuery: $withCountQuery, appends: $appends, groupBy: $groupBy);

    }

    public function saveBlog(array $data): void
    {
        $this->syncAuthor($data);
        $removeMetaImage = filter_var($data['remove_meta_image'] ?? false, FILTER_VALIDATE_BOOLEAN);
        unset($data['remove_meta_image']);

        $blog = $data['blog'] ?? null;
        $isExistingBlog = array_key_exists('blog', $data);
        $isDrafted = array_key_exists('is_drafted', $data);
        $isPublished = array_key_exists('is_published', $data);

        if ($isExistingBlog) {
            $this->deleteRemovedSummernoteImages($blog?->description, $data['description'] ?? '');
        }

        if ($isExistingBlog && $isDrafted) {
            $this->persistDraftFromBlog($blog, $data);
            return;
        }

        if (array_key_exists('thumbnail', $data)) {
            $fileName = fileUploader('blog/', APPLICATION_IMAGE_FORMAT, $data['thumbnail'],  $blog?->thumbnail ?? '');
            $data['thumbnail'] = $fileName;
        } else {
            $data['thumbnail'] = $blog?->thumbnail ?? '';
        }

        if (array_key_exists('meta_image', $data)) {
            $fileName = fileUploader('blog/meta-image/', APPLICATION_IMAGE_FORMAT, $data['meta_image'], $blog?->meta_image ?? '');
            $data['meta_image'] = $fileName;
        } elseif ($removeMetaImage) {
            fileRemover('blog/meta-image/', $blog?->meta_image ?? null);
            $data['meta_image'] = '';
        } else {
            $data['meta_image'] = $blog?->meta_image ?? '';
        }

        if ($isExistingBlog && $isPublished) {
            $data['status'] = 1;
            $oldDraftThumbnail = $blog?->draft?->thumbnail ?? '';

            $blog->update($data);

            if ($blog?->draft) {
                $blog->draft->delete();
            }

            if ($oldDraftThumbnail !== '' && $oldDraftThumbnail !== $data['thumbnail']) {
                fileRemover('blog/', $oldDraftThumbnail);
            }
        } elseif (!$isExistingBlog) {
            if ($isDrafted) {
                $data['status'] = 0;
            }

            $blog = $this->blogRepository->create(data: $data);

            if ($isDrafted) {
                $this->blogDraftRepository->create(data: array_merge($data, ['blog_id' => $blog->id]));
            }
        }
    }

    private function persistDraftFromBlog($blog, array $data): void
    {
        $existingDraft = $blog?->draft;
        $publishedThumbnail = $blog?->thumbnail ?? '';
        $oldDraftThumbnail = $existingDraft?->thumbnail ?? '';

        if (array_key_exists('thumbnail', $data)) {
            $oldFile = ($oldDraftThumbnail !== '' && $oldDraftThumbnail !== $publishedThumbnail) ? $oldDraftThumbnail : '';
            $fileName = fileUploader('blog/', APPLICATION_IMAGE_FORMAT, $data['thumbnail'], $oldFile);
            $data['thumbnail'] = $fileName;
        } elseif (!$existingDraft) {
            $data['thumbnail'] = $publishedThumbnail;
        } else {
            unset($data['thumbnail']);
        }

        unset($data['meta_image'], $data['blog'], $data['is_drafted']);

        if ($existingDraft) {
            $existingDraft->update($data);
        } else {
            $this->blogDraftRepository->create(data: array_merge($data, ['blog_id' => $blog->id]));
        }
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

    public function export(Collection $data): Collection|LengthAwarePaginator|\Illuminate\Support\Collection
    {
        return $data->map(function ($item) {
            $source = $item->is_published ? $item : $item->draft;
            return [
                'Id' => $item['readable_id'],
                'Category' => $source?->category?->name ?? 'N/A',
                'Title' => $source?->title ?? 'N/A',
                'Writer' => $source?->author?->name ?? 'N/A',
                'Publish Date' => $source?->published_at?->format('d M, Y') ?? 'N/A',
                'Status' => $item->status == 0 ? translate('inactive') : translate('Active'),
            ];
        });
    }

    public function search(array $criteria = [], array $relations = [], array $whereHasRelations = [], array $orderBy = [], ?int $limit = null, ?int $offset = null, array $withCountQuery = [], array $appends = [], array $groupBy = []): Collection|LengthAwarePaginator
    {
        $searchData = [];

        if (array_key_exists('search', $criteria) && $criteria['search'] != '') {
            $searchData['fields'] = ['title'];
            $searchData['relations'] = [
                'category' => ['name'],
            ];
            $searchData['value'] = $criteria['search'];
        }
        unset($criteria['search']);
        $data = $criteria;
        $whereBetweenCriteria = [];

        return $this->blogRepository->getBy(criteria: $data, searchCriteria: $searchData, whereInCriteria: $whereInCriteria ?? [], whereBetweenCriteria: $whereBetweenCriteria, whereHasRelations: $whereHasRelations, relations: $relations, orderBy: $orderBy, limit: $limit, offset: $offset, withCountQuery: $withCountQuery, appends: $appends, groupBy: $groupBy);
    }

}
