<?php

namespace Modules\UserManagement\Service\Interfaces;

use App\Service\BaseServiceInterface;
use Illuminate\Database\Eloquent\Model;

interface UserServiceInterface extends BaseServiceInterface
{
    public function mergeUserAccount(array $data, string $id): ?Model;
}
