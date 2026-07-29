<?php

namespace Modules\UserManagement\Service\Interfaces;

use App\Service\BaseServiceInterface;

interface DriverDetailServiceInterface extends BaseServiceInterface
{
    public function updateAvailability(array $data = []);
}
