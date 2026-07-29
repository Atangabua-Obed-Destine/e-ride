<?php

namespace Modules\BlogManagement\Service\Interfaces;

use App\Service\BaseServiceInterface;

interface BlogDraftServiceInterface extends BaseServiceInterface
{
    public function saveBlogDraft(array $data): void;
}
