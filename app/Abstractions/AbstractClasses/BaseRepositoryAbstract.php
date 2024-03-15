<?php

namespace App\Abstractions\AbstractClasses;

use App\Abstractions\Interfaces\RepositoryInterface;
use App\Services\Helper;
use App\Utils\Utils;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

abstract class BaseRepositoryAbstract implements RepositoryInterface
{

    /**
     * @var array
     */
    protected array $allowableFilters = [];

    /**
     * @var array
     */
    protected array $relationships = [];

    /**
     * BaseRepository constructor
     *
     * @param Model $model
     * @param string $databaseTableName
     */
    public function __construct(protected Model $model, protected string $databaseTableName) {}

    /**
     *
     * @return Model
     */
    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * Get all Models or entities
     *
     * @param array $columns
     * @param array $relations
     * @return Collection
     */
    public function all(array $columns = ['*'], array $relations = []): Collection
    {
        return DB::transaction(function () use ($columns, $relations) {
            return $this->model->with($relations)->sharedLock()->orderByDesc('created_at')->get($columns);
        });
    }

    /**
     * Get all Trashed Models or entities
     *
     * @return Collection
     */
    public function getAllTrashed(): Collection
    {
        return DB::transaction(function () {
            return $this->model->onlyTrashed()->sharedLock()->get();
        });
    }

    /**
     * Find Model by id
     *
     * @param int $modelId
     * @param array|string[] $columns
     * @param array $relations
     * @param array $appends
     * @param bool $useLock
     * @return Model|null
     */
    public function findById(int $modelId, array $columns = ['*'], array $relations = [], array $appends = [], bool $useLock = false): ?Model
    {
        $query = $this->model->select($columns)->with($relations);
        return $useLock ? $query->sharedLock()->find($modelId) : $query->find($modelId);
    }

    /**
     * This checks if a model attribute already exists without the queried model id
     *
     * @param int $modelId
     * @param array $whereClauses
     * @return bool
     */
    public function checkIfAlreadyExistsWithoutTheModel(int $modelId, array $whereClauses): bool
    {
        return (bool) $this->getModel()::where('id', '!=', $modelId)
            ->where($whereClauses)
            ->latest()
            ->first();
    }

    /**
     * Find Model by column name and value
     *
     * @param string $columnName
     * @param string $value
     * @param array $columns
     * @param array $relations
     * @return array
     */
    public function findByColumnAndValue(string $columnName, string $value, array $columns = ['*'], array $relations = []): array
    {
        try {
            return DB::transaction(function () use ($relations, $columns, $value, $columnName) {
                $records = [];
                $results = $this->model->with($relations)->select($columns)->where($columnName, $value)->sharedLock()->orderByDesc('id')->get();
                if (count($results)) {
                    foreach ($results as $result) {
                        $records[] = $result;
                    }
                }
                return $records;
            });
        } catch (\Exception $exception) {
            Log::error($exception);
            return [];
        }
    }

    /**
     * Find Model by column name and value
     *
     * @param string $columnName
     * @param string $value
     * @param array $columns
     * @param array $relations
     * @return Model|null
     */
    public function findWhereNotByColumnAndValue(string $columnName, string $value, array $relations = [], array $columns = ['*']): ?Model
    {
        return $this->model
            ->with($relations)
            ->select($columns)
            ->where($columnName, '!=', $value)
            ->sharedLock()
            ->first();
    }

    /**
     * Find Model by list of where clauses
     * Make sure that the keys in the $queries are also available in the list of $acceptedFilters otherwise, it would not work
     *
     * @param array $directWhereQueries
     * @param array $queryParameters
     * @param array $acceptedFilters
     * @param array $relations
     * @param array|string[] $columns
     * @return array
     */
    public function findByWhereValueClauses(
        array $directWhereQueries = [],
        array $queryParameters    = [],
        array $acceptedFilters    = [],
        array $relations          = [],
        array $columns            = ['*']
    ): array
    {
        try {
            $data    = [];
            $records = $this->getModel()->with(count($relations)
                ? $relations
                : $this->relationships
            )->select($columns)->where($directWhereQueries);
            $records = Utils::returnFilteredSearchedKeys($records, $queryParameters, count($acceptedFilters) ? $acceptedFilters : $this->allowableFilters)
                ->orderByDesc('updated_at')
                ->sharedLock()
                ->get();
            if (count($records)) {
                foreach ($records as $record) {
                    $data[] = $record;
                }
            }
            return $data;
        } catch (\Exception $exception) {
            Log::error($exception);
            return [];
        }
    }

    /**
     * Find Model by list of where clauses
     * Make sure that the keys in the $queries are also available in the list of $acceptedFilters otherwise, it would not work
     *
     * @param array $directWhereQueries
     * @param array $queryParameters
     * @param array $acceptedFilters
     * @param array $relations
     * @param array|string[] $columns
     * @return array
     */
    public function findByWhereValueClausesWithTrash(
        array $directWhereQueries = [],
        array $queryParameters    = [],
        array $acceptedFilters    = [],
        array $relations          = [],
        array $columns            = ['*']
    ): array
    {
        try {
            $data    = [];
            $records = $this->model->with($relations)->select($columns)->where($directWhereQueries);
            $records = Utils::returnFilteredSearchedKeys($records, $queryParameters, $acceptedFilters)
                ->orderByDesc('id')
                ->sharedLock()
                ->withTrashed()
                ->get();

            if (count($records)) {
                foreach ($records as $record) {
                    $data[] = $record;
                }
            }

            return $data;
        } catch (\Exception $exception) {
            Log::error($exception);
            return [];
        }
    }

    /**
     * Find Trashed model by id
     *
     * @param int $modelId
     * @return Model|null
     */
    public function findTrashedById(int $modelId): ?Model
    {
        return DB::transaction(function () use ($modelId) {
            return $this->model->withTrashed()->sharedLock()->find($modelId);
        });
    }

    /**
     * Find Trashed model by id
     *
     * @param int $modelId
     * @return Model|null
     */
    public function findOnlyTrashedById(int $modelId): ?Model
    {
        return DB::transaction(function () use ($modelId) {
            return $this->model->onlyTrashed()->sharedLock()->find($modelId);
        });
    }

    /**
     * This creates a new Model by the Model's properties
     *
     * @param array $attributes
     * @param array $relationships
     * @param bool $useLock
     * @return Model|null
     */
    public function createModel(array $attributes, array $relationships = [], bool $useLock = false): ?Model
    {
        /** @var Model|null $model */
        $model = null;
        DB::transaction(function () use (&$model, $attributes, $relationships) {
            $model = Utils::saveModelRecord(new $this->model, $attributes);
        });
        return $model ? $this->findById($model->id, ['*'], $relationships, [], $useLock) : null;
    }

    /**
     * This creates a new Model by the Model's properties
     *
     * @param Model $polymorphicModel
     * @param string $polymorphicMethod
     * @param array $attributes
     * @return Model|null
     */
    public function createPolymorphicModel(Model $polymorphicModel, string $polymorphicMethod, array $attributes): ?Model
    {
        /** @var Model|null $model */
        $model = null;
        DB::transaction(function () use (&$model, $attributes, $polymorphicMethod, $polymorphicModel) {
            $model = Utils::savePolymorphicRecord(new $this->model, $polymorphicModel, $polymorphicMethod, $attributes);
        });
        return $model ? $this->findById($model->id) : null;
    }

    /**
     * This updates an existing model by its id
     *
     * @param int $modelId
     * @param array $attributes
     * @return bool
     */
    public function updateById(int $modelId, array $attributes): bool
    {
        return DB::transaction(function () use ($attributes, $modelId) {
            $model = $this->findById($modelId);
            return $model->update($attributes);
        });
    }

    /**
     * This updates an existing model by its id
     *
     * @param int $modelId
     * @param array $attributes
     * @param array $relationships
     * @param array $columns
     * @return Model
     */
    public function updateByIdAndGetBackRecord(int $modelId, array $attributes, array $relationships = [], array $columns = ['*']): Model
    {
        return DB::transaction(function () use ($modelId, $attributes, $relationships, $columns) {
            $model = $this->findById($modelId);
            // Lock the rows for update
            $this->findById($modelId)->lockForUpdate()->get();
            $model->update($attributes);
            return $this->findById($modelId, $columns, $relationships);
        }, 5);
    }

    /**
     *
     * @param string $column
     * @param string $value
     * @param array $fields
     * @return bool
     */
    public function updateByWhereClause(string $column, string $value, array $fields): bool
    {
        return DB::transaction(function () use ($column, $value, $fields) {
            return DB::table($this->databaseTableName)->where($column, $value)->sharedLock()->update($fields);
        }, 5);
    }

    /**
     *
     * @param array $whereQueries
     * @param array $fields
     * @return bool
     */
    public function updateByWhereClauses(array $whereQueries, array $fields): bool
    {
        return DB::transaction(function () use ($whereQueries, $fields) {
            return DB::table($this->databaseTableName)->where($whereQueries)->sharedLock()->update($fields);
        }, 5);
    }

    /**
     * Soft-Deletes a model by its id
     *
     * @param int $modelId
     * @return bool
     */
    public function deleteById(int $modelId): bool
    {
        return $this->findById($modelId)->delete();
    }

    /**
     *
     * @param array $whereQueries
     * @return bool
     */
    public function deleteBy(array $whereQueries): bool
    {
        return $this->findSingleByWhereClause($whereQueries)->delete();
    }

    /**
     *
     * @param array $where
     * @return bool
     */
    public function deleteByWhere(array $where): bool
    {
        return $this->findSingleByWhereClause($where)->delete();
    }

    /**
     * Restores a soft-deleted model by id
     * @param int $modelId
     * @return bool
     */
    public function restoreById(int $modelId): bool
    {
        return $this->findOnlyTrashedById($modelId)->restore();
    }

    /**
     * This permanently deletes a record by model's id
     * @param int $modelId
     * @return bool
     */
    public function permanentlyDeleteById(int $modelId): bool
    {
        return $this->findTrashedById($modelId)->forceDelete();
    }

    /**
     *
     * @param array $queries
     * @param array $columns
     * @param array $relations
     * @return Model|null
     */
    public function findSingleByWhereClause(array $queries, array $columns = ['*'], array $relations = []): ?Model
    {
        $relations = count($relations) ? $relations : $this->relationships;
        return DB::transaction(function () use ($queries, $columns, $relations) {
            $query = $this->getModel()->with($relations)->select($columns);
            return Utils::getRecordUsingWhereArrays($query, $queries)->sharedLock()->latest()->first();
        });
    }

    /**
     *
     * @param string $columnToCount
     * @param array $queries
     * @return int
     */
    public function countRecords(string $columnToCount, array $queries = []): int
    {
        return $this->getModel()::where($queries)->sharedLock()->count($columnToCount);
    }

    /**
     *
     * @param string $columnToCount
     * @param string $dateValue
     * @param array $queries
     * @return int
     */
    public function countRecordByDate(string $columnToCount, string $dateValue, array $queries = []): int
    {
        return $this->getModel()::whereDate('created_at', $dateValue)->where($queries)->sharedLock()->count($columnToCount);
    }

    /**
     *
     * @param string $columnToSum
     * @param array $queries
     * @return float
     */
    public function sumRecords(string $columnToSum, array $queries = []): float
    {
        return $this->getModel()::where($queries)->sharedLock()->sum($columnToSum);
    }

    /**
     *
     * @param int $id
     * @param string $columnToSum
     * @param array $queries
     * @return float
     */
    public function sumRecordsByWhereAndId(int $id, string $columnToSum, array $queries = []): float
    {
        return $this->getModel()::where('id', $id)->where($queries)->sharedLock()->sum($columnToSum);
    }

    /**
     *
     * @param int $id
     * @param string $columnToSum
     * @param array $queries
     * @return float
     */
    public function sumRecordsByWhereNotAndId(int $id, string $columnToSum, array $queries = []): float
    {
        return $this->getModel()::where('id',"!=", $id)->where($queries)->sharedLock()->sum($columnToSum);
    }

    /**
     *
     * @param int $id
     * @param string $columnToSum
     * @param array $queries
     * @return float
     */
    public function sumRecordsByWhereNotIdAndAttributes(int $id, string $columnToSum, array $queries = []): float
    {
        return $this->getModel()::where('id',"!=", $id)->where($queries)->sharedLock()->sum($columnToSum);
    }

    /**
     *
     * @param int $id
     * @param string $columnToSum
     * @return float
     */
    public function sumRecordsWhereNotId(int $id, string $columnToSum): float
    {
        return $this->getModel()::where('id',"!=", $id)->sharedLock()->sum($columnToSum);
    }

    /**
     *
     * @param string $columnName
     * @return array
     */
    public function getAllTokens(string $columnName = 'reference'): array
    {
        return DB::transaction(function () use ($columnName) {
            return $this->getModel()::sharedLock()->pluck($columnName)->toArray();
        });
    }

    /**
     *
     * @param string $dateValue
     * @param array $queries
     * @return mixed
     */
    public function fetchRecordByDate(string $dateValue, array $queries = [])
    {
        return $this->getModel()::whereDate('created_at', $dateValue)->whereIn('status', $queries['status'])->where('is_tracked',$queries['is_tracked'])->sharedLock()->get();
    }

    /**
     * @param int $recordId
     * @param array $fields
     * @return Model
     */
    public function updateFieldsById(int $recordId, array $fields)
    {
        return (new Helper())::saveModelRecord($this->getModel()::find($recordId), $fields);
    }

    /**
     * @param array $queries
     * @return mixed
     */
    public function findByWhereWithOutRelation(array $queries)
    {
        return $this->getModel()::where($queries)->sharedLock()->first();
    }

    /**
     * @param array $queries
     * @return mixed
     */
    public function getByWhereWithOutRelation(array $queries)
    {
        return $this->getModel()::where($queries)->sharedLock()->orderByDesc('id')->get();
    }

    /**
     * @param string $provider
     * @param array $date
     * @return mixed[]
     */
    public function findByWhereWithSpecifiedAttributes(string $provider, array $date = [])
    {
        return $this->getModel()::where('service_provider', $provider)
            ->whereBetween('created_at', $date)
            ->select(DB::raw("COUNT(id) as count"), "status")
            ->groupBy("status")
            ->get();
    }

    /**
     * @param string $column
     * @param array $queries
     * @return Builder[]|Collection
     */
    public function getByWhereIn(string $column, array $queries): Collection|array
    {
        return $this->model::with($this->relationships)->whereIn($column, $queries)->sharedLock()->orderByDesc('id')->get();
    }
}
