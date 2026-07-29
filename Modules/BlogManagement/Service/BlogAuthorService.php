<?php

namespace Modules\BlogManagement\Service;

use App\Service\BaseService;
use Illuminate\Support\Collection;
use Modules\BlogManagement\Entities\BlogAuthor;
use Modules\BlogManagement\Repository\BlogAuthorRepositoryInterface;
use Modules\BlogManagement\Service\Interfaces\BlogAuthorServiceInterface;

class BlogAuthorService extends BaseService implements BlogAuthorServiceInterface
{
    protected BlogAuthorRepositoryInterface $blogAuthorRepository;

    public function __construct(BlogAuthorRepositoryInterface $blogAuthorRepository)
    {
        parent::__construct($blogAuthorRepository);
        $this->blogAuthorRepository = $blogAuthorRepository;
    }

    public function syncAuthorByName(?string $name): ?BlogAuthor
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        return $this->blogAuthorRepository->findOneBy(criteria: ['name' => $name])
            ?? $this->blogAuthorRepository->create(data: ['name' => $name]);
    }

    public function getAuthorNames(): Collection
    {
        return $this->blogAuthorRepository
            ->getAll(orderBy: ['name' => 'asc'])
            ->pluck('name');
    }

    public function findBySlug(string $slug): ?BlogAuthor
    {
        return $this->blogAuthorRepository->findOneBy(criteria: ['slug' => $slug]);
    }
}
