<?php

namespace Modules\UserManagement\Service\Interfaces;

use App\Service\BaseServiceInterface;
use Illuminate\Database\Eloquent\Model;

interface ReferralCustomerServiceInterface extends BaseServiceInterface
{
    public function createReferralEarning(Model $newUser, Model $referralUser): void;
}
