<?php

namespace Modules\UserManagement\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Modules\UserManagement\Entities\DriverIdentityVerification;
use Modules\UserManagement\Repository\DriverIdentityVerificationRepositoryInterface;

class DriverIdentityVerificationRepository extends BaseRepository implements DriverIdentityVerificationRepositoryInterface
{
    public function __construct(DriverIdentityVerification $model)
    {
        parent::__construct($model);
    }
}
