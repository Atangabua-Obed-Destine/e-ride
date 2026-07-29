<?php

namespace Modules\TripManagement\Service\Interfaces;

use App\Service\BaseServiceInterface;

interface TripNavigationServiceInterface extends BaseServiceInterface
{
    public function getLockedTrip(array $data = []): mixed;
}
