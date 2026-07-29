<?php

namespace App\Repository\Eloquent;

use App\Repository\EloquentRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class BaseRepository implements EloquentRepositoryInterface
{
    protected Model $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    // Example criteria usage
    // $criteria = [
    // 'category_id' => 1, // Find records with category_id equal to 1
    // ['created_at' ,'>=', '2021-01-01'], // Find records created after 2021-01-01
    // ];


// Example groupBy usage
//$groupBy = [
//'users.id', // Group by the user's ID
//'users.role', // Group by the user's role
//'posts.category_id', // Group by the category_id field in the posts relation
//'comments.user_id', // Group by the user_id field in the comments relation
//    // Add more columns as needed
//];

//    $relations = [
//        'posts' => [ // Eager load posts with specific conditions
//            ['published', '=', true],
//            ['category_id', '=', 1],
//        ],
//        'comments'=> [], // Eager load comments without conditions
//        // Add more relations as needed
//    ];
//
//    $orderBy = [
//        'created_at' => 'desc', // Order by created_at field in descending order
//        // Add more order by clauses as needed
//    ];
    protected function prepareModelForRelationAndOrder(array $relations = [], array $orderBy = []): Model|Builder
    {
        $model = $this->model;
        if (!empty($relations)) {
            foreach ($relations as $relation => $conditions) {
                if (is_array($conditions)) {
                    $model = $model->with([$relation => function ($query) use ($conditions) {
                        foreach ($conditions as $condition) {
                            $query->where($condition[0], $condition[1], $condition[2]);
                        }
                    }]);
                } elseif (is_callable($conditions)) {
                    // Handle closures directly without misinterpreting them
                    $model = $model->with([$relation => $conditions]);
                } else {
                    $model = $model->with($relations);
                }
            }

        }

        if (!empty($orderBy)) {
            foreach ($orderBy as $field => $direction) {
                $model = $model->orderBy($field, $direction);
            }
        }
        return $model;
    }

    protected function withOrWithOutTrashDataQuery($query, bool $onlyTrashed = false, bool $withTrashed = false)
    {
        if ($onlyTrashed && !$withTrashed) {
            $query->onlyTrashed();
        }
        if (!$onlyTrashed && $withTrashed) {
            $query->withTrashed();
        }
    }

    //$withCountQuery =[
    //'comments'=>[], // Just count the total comments related to each record
    //'likes', // Just count the total likes related to each record
    //'posts' => [ // Count posts with specific conditions
    //['published', '=', true],
    //['category_id', '=', 1],
    //],
    // You can add more relationships with or without conditions as needed
    //];
    protected function withCountQuery($query, array $withCountQuery = [])
    {
        foreach ($withCountQuery as $relation => $conditions) {
            if (is_array($conditions)) {
                $query->withCount([$relation => function ($query) use ($conditions) {
                    foreach ($conditions as $condition) {
                        $query->where($condition[0], $condition[1], $condition[2]);
                    }
                }]);
            } else {
                $query->withCount($relation);
            }
        }
    }
//$whereHasRelations = [
//'posts' => ['status' => 'published', 'category' => 'technology'],
//'posts.comments' => ['approved' => true],
//    // Add more relations and conditions as needed
//];

//    $searchCriteria = [
//        'relations' => [
//              'posts' => ['title', 'content'],
//                  ],
//        'fields' => ['name', 'email'], // Fields to search within
//        'value' => 'example_value', // The value to search for
//    ];
    protected function searchQuery($query, array $searchCriteria = [])
    {
        if (isset($searchCriteria['value']) && !empty($searchCriteria['value'])) {
            $value = $searchCriteria['value'];

            $query->where(function ($query) use ($value, $searchCriteria) {
                // Search in main model fields
                if (isset($searchCriteria['fields']) && is_array($searchCriteria['fields'])) {
                    $query->where(function ($query) use ($value, $searchCriteria) {
                        foreach ($searchCriteria['fields'] as $field) {
                            $query->orWhere($field, 'like', '%' . $value . '%');
                        }
                    });
                }

                // Search in related model fields
                if (isset($searchCriteria['relations']) && is_array($searchCriteria['relations'])) {
                    foreach ($searchCriteria['relations'] as $relation => $fields) {
                        $query->orWhereHas($relation, function ($relationQuery) use ($value, $fields) {
                            $relationQuery->where(function ($query) use ($value, $fields) {
                                foreach ($fields as $field) {
                                    $query->orWhere($field, 'like', '%' . $value . '%');
                                }
                            });
                        });
                    }
                }
            });
        }

    }


    public function getAll(array $relations = [], array $orderBy = [], ?int $limit = null, ?int $offset = null, bool $onlyTrashed = false, bool $withTrashed = false, array $withCountQuery = [], array $groupBy = []): Collection|LengthAwarePaginator
    {
        $model = $this->prepareModelForRelationAndOrder(relations: $relations, orderBy: $orderBy)
            ->when(($onlyTrashed || $withTrashed), function ($query) use ($onlyTrashed, $withTrashed) {
                $this->withOrWithOutTrashDataQuery($query, $onlyTrashed, $withTrashed);
            })
            ->when(!empty($withCountQuery), function ($query) use ($withCountQuery) {
                $this->withCountQuery($query, $withCountQuery);
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
        if ($limit) {
            return $model->paginate(perPage: $limit, page: $offset ?? 1);
        }
        return $model->get();
    }

    public function getBy(array $criteria = [], array $searchCriteria = [], array $whereInCriteria = [], array $whereBetweenCriteria = [], array $whereHasRelations = [], array $withAvgRelations = [], array $relations = [], array $orderBy = [], ?int $limit = null, ?int $offset = null, bool $onlyTrashed = false, bool $withTrashed = false, array $withCountQuery = [], array $appends = [], array $groupBy = []): Collection|LengthAwarePaginator
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
        if ($limit) {
            return !empty($appends) ? $model->paginate(perPage: $limit, page: $offset ?? 1)->appends($appends) : $model->paginate(perPage: $limit, page: $offset ?? 1);
        }
        return $model->get();
    }

    public function create(array $data): ?Model
    {
        return $this->model->create($data);
    }

    public function createMany(array $data)
    {
        return $this->model->insert($data);
    }

    public function update(int|string $id, array $data = []): ?Model
    {
        $resource = $this->model->find($id);
        if ($resource && is_array($data) && count($data) > 0) {
            $resource->fill($data)->save();
        }
        return $resource;
    }

    public function updatedBy(array $criteria = [], array $whereInCriteria = [], array $data = [], bool $withTrashed = false)
    {
        if ($criteria && $withTrashed) {
            $this->model->withTrashed()->where($criteria)->update($data);
        } elseif ($whereInCriteria && $withTrashed) {
            $this->model->withTrashed()->when(!empty($whereInCriteria), function ($whereInQuery) use ($whereInCriteria) {
                foreach ($whereInCriteria as $column => $values) {
                    $whereInQuery->whereIn($column, $values);
                }
            })->update($data);
        } elseif ($whereInCriteria) {
            $this->model->when(!empty($whereInCriteria), function ($whereInQuery) use ($whereInCriteria) {
                foreach ($whereInCriteria as $column => $values) {
                    $whereInQuery->whereIn($column, $values);
                }
            })->update($data);
        } elseif ($criteria) {
            $this->model->where($criteria)->update($data);
        }
    }

    public function findOne(int|string $id, array $relations = [], array $withAvgRelations = [], array $whereHasRelations = [], array $withCountQuery = [], bool $withTrashed = false, bool $onlyTrashed = false): ?Model
    {
        return $this->prepareModelForRelationAndOrder(relations: $relations)
            ->when(!empty($withCountQuery), function ($query) use ($withCountQuery) {
                $this->withCountQuery($query, $withCountQuery);
            })
            ->when(($onlyTrashed || $withTrashed), function ($query) use ($onlyTrashed, $withTrashed) {
                $this->withOrWithOutTrashDataQuery($query, $onlyTrashed, $withTrashed);
            })->when(!empty($withAvgRelations), function ($query) use ($withAvgRelations) {
                foreach ($withAvgRelations as $relation) {
                    $query->withAvg($relation[0], $relation[1]);
                }
            })->when(!empty($whereHasRelations), function ($whereHasQuery) use ($whereHasRelations) {
                foreach ($whereHasRelations as $relation => $conditions) {

                    $whereHasQuery->whereHas($relation, function ($query) use ($conditions) {
                        foreach ($conditions as $field => $value) {
                            if (is_array($value) && count($value) === 3) {
                                // Handle complex conditions with custom operators
                                [$field, $operator, $val] = $value;
                                $query->where($field, $operator, $val);
                            } elseif (is_array($value)) {
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
            ->find($id);
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
                                // Handle complex conditions with custom operators
                                [$field, $operator, $val] = $value;
                                $query->where($field, $operator, $val);
                            } elseif (is_array($value)) {
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

    public function delete(int|string $id): bool
    {
        return $this->model
            ->find($id)
            ->delete();
    }

    public function deleteBy(array $criteria): bool
    {
        return $this->model
            ->where($criteria)
            ->delete();
    }

    public function permanentDelete(int|string $id): bool
    {
        return $this->model->withTrashed()
            ->find($id)
            ->forceDelete();
    }

    public function permanentDeleteBy(array $criteria): bool
    {
        return $this->model->withTrashed()
            ->where($criteria)
            ->forceDelete();
    }

    public function restoreData(int|string $id): Mixed
    {
        return $this->model->onlyTrashed()
            ->find($id)->restore();
    }
}
