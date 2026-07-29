<?php

namespace Modules\FareManagement\Repository\Eloquent;

use App\Repository\Eloquent\BaseRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Modules\FareManagement\Entities\SurgePricing;
use Modules\FareManagement\Repository\SurgePricingRepositoryInterface;

class SurgePricingRepository extends BaseRepository implements SurgePricingRepositoryInterface
{
    public function __construct(SurgePricing $model)
    {
        parent::__construct($model);
    }

    public function findOneBy(array $criteria = [], array $whereInCriteria = [], array $whereBetweenCriteria = [], array $withAvgRelations = [], array $relations = [], array $whereHasRelations = [], array $withCountQuery = [], array $orderBy = [], bool $withTrashed = false, bool $onlyTrashed = false): ?Model
    {
        return $this->prepareModelForRelationAndOrder(relations: $relations)
            ->where($criteria)
            ->when(!empty($whereInCriteria), function ($whereInQuery) use ($whereInCriteria) {
                foreach ($whereInCriteria as $column => $values) {
                    $whereInQuery->whereIn($column, $values);
                }
            })
            ->when(!empty($whereBetweenCriteria), function ($whereBetweenQuery) use ($whereBetweenCriteria) {
                foreach ($whereBetweenCriteria as $column => $range) {
                    $whereBetweenQuery->whereBetween($column, $range);
                }
            })
            ->when(!empty($whereHasRelations), function ($whereHasQuery) use ($whereHasRelations) {
                foreach ($whereHasRelations as $relation => $conditions) {

                    $whereHasQuery->whereHas($relation, function ($query) use ($conditions) {
                        foreach ($conditions as $field => $value) {
                            if (is_array($value) && count($value) === 3) {
                                [$field, $operator, $val] = $value;

                                // Special case for end_date
                                if ($field === 'end_date' && $operator === '>=' ) {
                                    $query->where(function ($q) use ($field, $operator, $val) {
                                        $q->where($field, $operator, $val)
                                            ->orWhere($field, 'unlimited')
                                            ->orWhereNull($field);
                                    });
                                } else {
                                    $query->where($field, $operator, $val);
                                }
                            }elseif (is_array($value)) {
                                // Handle OR conditions for arrays (e.g., ['ongoing', 'accepted', 'completed'])
                                $query->where(function ($subQuery) use ($field, $value) {
                                    foreach ($value as $v) {
                                        $subQuery->orWhere($field, $v);
                                    }
                                });
                            } else {
                                // Handle single key-value pairs
                                $query->where($field, $value);
                            }
                        }
                    });
                }
            })
            ->when(!empty($withCountQuery), function ($query) use ($withCountQuery) {
                $this->withCountQuery($query, $withCountQuery);
            })
            ->when(($onlyTrashed || $withTrashed), function ($query) use ($onlyTrashed, $withTrashed) {
                $this->withOrWithOutTrashDataQuery($query, $onlyTrashed, $withTrashed);
            })->when(!empty($withAvgRelations), function ($query) use ($withAvgRelations) {
                foreach ($withAvgRelations as $relation) {
                    $query->withAvg($relation[0], $relation[1]);
                }
            })->when(!empty($orderBy), function ($query) use ($orderBy) {
                foreach ($orderBy as $column => $order) {
                    $query->orderBy($column, $order);
                }
            })
            ->first();
    }

    public function getSurgePricingListForChecking(int|string $zoneId, array $criteria = [], array $searchCriteria = [], array $whereInCriteria = [], array $whereBetweenCriteria = [], array $whereHasRelations = [], array $withAvgRelations = [], array $relations = [], array $orderBy = [], ?int $limit = null, ?int $offset = null, bool $onlyTrashed = false, bool $withTrashed = false, array $withCountQuery = [], array $appends = [], array $groupBy = []): Collection|LengthAwarePaginator
    {
        $model = $this->prepareModelForRelationAndOrder(relations: $relations, orderBy: $orderBy)
            ->when(!empty($criteria), function ($whereQuery) use ($criteria) {
                $whereQuery->where($criteria);
            })->when(!empty($whereInCriteria), function ($whereInQuery) use ($whereInCriteria) {
                foreach ($whereInCriteria as $column => $values) {
                    $whereInQuery->whereIn($column, $values);
                }
            })->when(!empty($whereHasRelations), function ($whereHasQuery) use ($whereHasRelations) {
                foreach ($whereHasRelations as $relation => $conditions) {

                    $whereHasQuery->whereHas($relation, function ($query) use ($conditions) {
                        foreach ($conditions as $field => $value) {
                            if (is_int($field) && is_array($value) && count($value) === 3) {
                                [$f, $operator, $val] = $value;
                                $query->where($f, $operator, $val);
                            } elseif (is_array($value)) {
                                $query->whereIn($field, $value);
                            } else {
                                $query->where($field, $value);
                            }
                        }
                    });
                }
            })->when(!empty($whereBetweenCriteria), function ($whereBetweenQuery) use ($whereBetweenCriteria) {
                foreach ($whereBetweenCriteria as $column => $range) {
                    $whereBetweenQuery->whereBetween($column, $range);
                }
            })->when(!empty($searchCriteria), function ($whereQuery) use ($searchCriteria) {
                $this->searchQuery($whereQuery, $searchCriteria);
            })->when(($onlyTrashed || $withTrashed), function ($query) use ($onlyTrashed, $withTrashed) {
                $this->withOrWithOutTrashDataQuery($query, $onlyTrashed, $withTrashed);
            })
            ->when(!empty($withCountQuery), function ($query) use ($withCountQuery) {
                $this->withCountQuery($query, $withCountQuery);
            })->when(!empty($withAvgRelations), function ($query) use ($withAvgRelations) {
                foreach ($withAvgRelations as $relation) {
                    $query->withAvg($relation['relation'], $relation['column']);
                }
            })->when(!empty($groupBy), function ($query) use ($groupBy) {
                $selectFields = []; // Prepare an array to hold select fields
                foreach ($groupBy as $groupColumn) {
                    if (str_ends_with($groupColumn, 'created_at')) {
                        // Group by the date part of the created_at field
                        $query->groupBy(DB::raw('DATE(' . $groupColumn . ')'));
                        $selectFields[] = DB::raw('DATE(' . $groupColumn . ') as ' . $groupColumn); // Select the date part
                    } else {
                        $query->groupBy($groupColumn);
                        $selectFields[] = $groupColumn; // Select the original group column
                    }
                }

                // Update the select statement to include the group columns
                $query->select($selectFields);
            });
        $model = $model->where(function ($query1) use ($zoneId) {
            $query1->where('zone_setup_type', 'all')->orWhereHas('surgePricingZones', function ($query2) use ($zoneId) {
                $query2->where('zone_id', $zoneId);
            });
        });


        if ($limit) {
            return !empty($appends) ? $model->paginate(perPage: $limit, page: $offset ?? 1)->appends($appends) : $model->paginate(perPage: $limit, page: $offset ?? 1);
        }
        return $model->get();
    }
}
