<?php

namespace Modules\BlogManagement\Service\Interfaces;

use App\Service\BaseServiceInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

interface BlogSettingServiceInterface extends BaseServiceInterface
{
    public function updateAppContents(array $data): void;
}
