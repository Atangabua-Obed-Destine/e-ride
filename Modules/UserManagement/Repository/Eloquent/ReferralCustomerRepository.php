<?php

namespace Modules\UserManagement\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Modules\UserManagement\Entities\ReferralCustomer;
use Modules\UserManagement\Repository\ReferralCustomerRepositoryInterface;

class ReferralCustomerRepository extends BaseRepository implements ReferralCustomerRepositoryInterface
{
    public function __construct(ReferralCustomer $model)
    {
        parent::__construct($model);
    }
}
