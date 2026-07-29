<?php

namespace Modules\TripManagement\Service;


use App\Service\BaseService;
use Modules\TripManagement\Repository\TempTripNotificationRepositoryInterface;
use Modules\TripManagement\Service\Interfaces\TempTripNotificationServiceInterface;

class TempTripNotificationService extends BaseService implements TempTripNotificationServiceInterface
{
    protected $tempTripNotificationRepository;

    public function __construct(TempTripNotificationRepositoryInterface $tempTripNotificationRepository)
    {
        parent::__construct($tempTripNotificationRepository);
        $this->tempTripNotificationRepository = $tempTripNotificationRepository;
    }

    // Add your specific methods related to TempTripNotificationService here
    public function getData(array $data = []): mixed
    {
        return $this->tempTripNotificationRepository->getData(criteria: $data, relations: ['user'], orderBy: ['id' => 'desc'], whereNotInCriteria: ['user_id', [auth('api')->id()]]);
    }

    public function createMany(array $data): mixed
    {
        return $this->tempTripNotificationRepository->createMany(data: $data);
    }
}
