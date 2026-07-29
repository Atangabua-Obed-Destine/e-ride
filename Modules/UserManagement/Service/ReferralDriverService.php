<?php

namespace Modules\UserManagement\Service;

use App\Service\BaseService;
use Modules\UserManagement\Repository\ReferralDriverRepositoryInterface;
use Modules\UserManagement\Service\Interfaces\ReferralDriverServiceInterface;

class ReferralDriverService extends BaseService implements Interfaces\ReferralDriverServiceInterface
{
    protected $referralDriverRepository;

    public function __construct(ReferralDriverRepositoryInterface $referralDriverRepository)
    {
        parent::__construct($referralDriverRepository);
        $this->referralDriverRepository = $referralDriverRepository;
    }
}
