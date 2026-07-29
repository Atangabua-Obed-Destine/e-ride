<?php

namespace Modules\BlogManagement\Service\Interfaces;

use App\Service\BaseServiceInterface;
use Illuminate\Support\Collection;
use Modules\BlogManagement\Entities\BlogAuthor;

interface BlogAuthorServiceInterface extends BaseServiceInterface
{
    public function syncAuthorByName(?string $name): ?BlogAuthor;

    public function getAuthorNames(): Collection;

    public function findBySlug(string $slug): ?BlogAuthor;
}
