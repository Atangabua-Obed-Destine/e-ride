<?php

namespace Modules\TripManagement\Service;


use App\Service\BaseService;
use Modules\TripManagement\Repository\TripNavigationRepositoryInterface;
use Modules\TripManagement\Service\Interfaces\TripNavigationServiceInterface;

class TripNavigationService extends BaseService implements TripNavigationServiceInterface
{
    protected $tripNavigationRepository;

    public function __construct(TripNavigationRepositoryInterface $tripNavigationRepository)
    {
        parent::__construct($tripNavigationRepository);
        $this->tripNavigationRepository = $tripNavigationRepository;
    }

    public function getLockedTrip(array $data = []): mixed
    {
        return $this->tripNavigationRepository->getLockedTrip(data: $data);
    }
}
