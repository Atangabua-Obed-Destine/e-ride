<?php

namespace Modules\UserManagement\Service;

use App\Service\BaseService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\UserManagement\Repository\Eloquent\NewsletterSubscriptionRepository;
use Modules\UserManagement\Service\Interfaces\NewsletterSubscriptionServiceInterface;

class NewsletterSubscriptionService extends BaseService implements NewsletterSubscriptionServiceInterface
{
    protected $newsletterSubscriptionRepository;
    public function __construct(NewsletterSubscriptionRepository $newsletterSubscriptionRepository)
    {
        parent::__construct($newsletterSubscriptionRepository);
        $this->newsletterSubscriptionRepository = $newsletterSubscriptionRepository;
    }

    public function index(array $criteria = [], array $relations = [], array $whereHasRelations = [], array $orderBy = [], ?int $limit = null, ?int $offset = null, array $withCountQuery = [], array $appends = [], array $groupBy = []): Collection|LengthAwarePaginator
    {
        $data = [];
        $searchData = [];
        if (array_key_exists('search', $criteria) && $criteria['search'] != '') {
            $searchData['fields'] = ['email'];
            $searchData['value'] = $criteria['search'];
        }

        return $this->newsletterSubscriptionRepository->getBy(criteria: $data, searchCriteria: $searchData, whereInCriteria: [], whereBetweenCriteria: [], whereHasRelations: $whereHasRelations, relations: $relations, orderBy: $orderBy, limit: $limit, offset: $offset, withCountQuery: $withCountQuery, appends: $appends, groupBy: $groupBy);
    }
}
