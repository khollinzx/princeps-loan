<?php

namespace App\Abstractions\Interfaces;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface RepositoryInterface
{

    /**
     * @return Model
     */
    public function getModel() :Model;

    /**
     * Get all Models or entities
     *
     * @param array $columns
     * @param array $relations
     * @return Collection
     */
    public function all(array $columns = ['*'], array $relations= []): Collection;

    /**
     * Get all Trashed Models or entities
     *
     * @return Collection
     */
    public function getAllTrashed(): Collection;

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
    public function findById(int $modelId, array $columns = ['*'], array $relations= [], array $appends = [], bool $useLock = false): ?Model;

    /**
     * This checks if a model attribute already exists without the queried model id
     *
     * @param int $modelId
     * @param array $whereClauses
     * @return bool
     */
    public function checkIfAlreadyExistsWithoutTheModel(int $modelId, array $whereClauses): bool;

    /**
     * Find Trashed model by id
     *
     * @param int $modelId
     * @return Model|null
     */
    public function findTrashedById(int $modelId): ?Model;

    /**
     * @param int $modelId
     * @return Model|null
     */
    public function findOnlyTrashedById(int $modelId): ?Model;

    /**
     * @param string $columnName
     * @param string $value
     * @param array $columns
     * @param array $relations
     * @return array
     */
    public function findByColumnAndValue(string $columnName, string $value, array $columns = ['*'], array $relations = []): array;

    /**
     * Find Model by list of where clauses
     * Make sure that the keys in the $queries are also available in the list of $acceptedFilters otherwise, it would not work
     *
     * @param array $directWhereQueries = e.g ['id' => 1]
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
    ): array;

    /**
     *
     * @param array $directWhereQueries
     * @param array $queryParameters
     * @param array $acceptedFilters
     * @param array $relations
     * @param array $columns
     * @return array
     */
    public function findByWhereValueClausesWithTrash(
        array $directWhereQueries = [],
        array $queryParameters    = [],
        array $acceptedFilters    = [],
        array $relations          = [],
        array $columns            = ['*']
    ): array;

    /**
     * This creates a new Model by the Model's properties
     *
     * @param array $attributes
     * @param array $relationships
     * @param bool $useLock
     */
    public function createModel(array $attributes, array $relationships = [], bool $useLock = false);

    /**
     * This creates a new Polymorphic Model by the Model's properties
     *
     * @param array $attributes
     * @param string $polymorphicMethod
     * @param Model $polymorphicModel
     */
    public function createPolymorphicModel(Model $polymorphicModel, string $polymorphicMethod, array $attributes);

    /**
     * This updates an existing model by its id
     *
     * @param int $modelId
     * @param array $attributes
     * @return bool
     */
    public function updateById(int $modelId, array $attributes): bool;

    /**
     * This updates an existing model by its id
     *
     * @param int $modelId
     * @param array $attributes
     * @param array $relationships
     * @param array $columns
     * @return Model
     */
    public function updateByIdAndGetBackRecord(int $modelId, array $attributes, array $relationships = [], array $columns = ['*']): Model;

    /**
     *
     * @param string $column
     * @param string $value
     * @param array $fields
     * @return bool
     */
    public function updateByWhereClause(string $column, string $value, array $fields): bool;

    /**
     *
     * @param array $whereQueries
     * @param array $fields
     * @return bool
     */
    public function updateByWhereClauses(array $whereQueries, array $fields): bool;

    /**
     * Soft-Deletes a model by its id
     *
     * @param int $modelId
     * @return bool
     */
    public function deleteById(int $modelId): bool;

    /**
     * Soft-Deletes a model by its id
     *
     * @param array $whereQueries
     * @return bool
     */
    public function deleteBy(array $whereQueries): bool;

    /**
     *
     * Restores a soft-deleted model by id
     * @param int $modelId
     * @return bool
     */
    public function restoreById(int $modelId): bool;

    /**
     *
     * This permanently deletes a record by model's id
     * @param int $modelId
     * @return bool
     */
    public function permanentlyDeleteById(int $modelId): bool;

    /**
     *
     * @param array $queries
     * @param array $columns
     * @param array $relations
     * @return Model|null
     */
    public function findSingleByWhereClause(array $queries, array $columns = ['*'], array $relations = []): ?Model;

    /**
     *
     * @param string $columnToCount
     * @param array $queries
     * @return int
     */
    public function countRecords(string $columnToCount, array $queries = []): int;

    /**
     *
     * @param string $columnToCount
     * @param string $dateValue
     * @param array $queries
     * @return int
     */
    public function countRecordByDate(string $columnToCount, string $dateValue, array $queries = []): int;

    /**
     *
     * @param string $columnToSum
     * @param array $queries
     * @return float
     */
    public function sumRecords(string $columnToSum, array $queries = []): float;

    /**
     *
     * @param int $id
     * @param string $columnToSum
     * @return float
     */
    public function sumRecordsWhereNotId(int $id, string $columnToSum): float;

    /**
     *
     * @param int $id
     * @param string $columnToSum
     * @param array $queries
     * @return float
     */
    public function sumRecordsByWhereAndId(int $id, string $columnToSum, array $queries = []): float;

    /**
     *
     * @param int $id
     * @param string $columnToSum
     * @param array $queries
     * @return float
     */
    public function sumRecordsByWhereNotAndId(int $id, string $columnToSum, array $queries = []): float;

    /**
     *
     * @param string $columnName
     * @return array
     */
    public function getAllTokens(string $columnName): array;

    /**
     * @param string $column
     * @param array $queries
     * @return Builder[]|Collection
     */
    public function getByWhereIn(string $column, array $queries): Collection|array;
}
