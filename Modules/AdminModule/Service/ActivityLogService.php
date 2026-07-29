<?php

namespace Modules\AdminModule\Service;

use App\Repository\EloquentRepositoryInterface;
use App\Service\BaseService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\AdminModule\Repository\ActivityLogRepositoryInterface;
use Modules\AdminModule\Service\Interfaces\ActivityLogServiceInterface;

class ActivityLogService extends BaseService implements Interfaces\ActivityLogServiceInterface
{
    protected $activityLogRepository;

    public function __construct(ActivityLogRepositoryInterface $activityLogRepository)
    {
        parent::__construct($activityLogRepository);
        $this->activityLogRepository = $activityLogRepository;
    }

    public function log(array $data): Collection|LengthAwarePaginator
    {
        $criteria = [
            'logable_type' => $data['logable_type']
        ];
        if (array_key_exists('user_type', $data)) {
            $criteria['user_type'] = $data['user_type'];
        }
        if (array_key_exists('id', $data)) {
            $criteria['logable_id'] = $data['id'];
        }
        $relations = ['users'];
        $searchCriteria = [];
        if (array_key_exists('search', $data)) {
            $searchCriteria = [
                'relations' => [
                    'users' => ['email'],
                ],
                'value' => $data['search'], // The value to search for

            ];
        }

        $appends = [
            'id' => array_key_exists('id', $data) ? $data['id'] : null,
            'search' => array_key_exists('search', $data) ? $data['search'] : null,
        ];
        if (array_key_exists('file', $data)) {
            return $this->activityLogRepository->getBy(criteria: $criteria, searchCriteria: $searchCriteria, relations: $relations, orderBy: ['created_at' => 'desc']);
        }

        return $this->activityLogRepository->getBy(criteria: $criteria, searchCriteria: $searchCriteria, relations: $relations, orderBy: ['created_at' => 'desc'], limit: paginationLimit(), offset: $data['page'] ?? 1);
    }
}
