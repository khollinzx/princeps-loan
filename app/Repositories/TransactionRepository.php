<?php

namespace App\Repositories;

use App\Abstractions\AbstractClasses\BaseRepositoryAbstract;
use App\Models\AgentOtp;
use App\Models\AgentPurse;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TransactionRepository extends BaseRepositoryAbstract
{

    /**
     * @var string
     */
    protected string $databaseTableName = 'transactions';

    /**
     *
     * @param Transaction $model
     */
    public function __construct(Transaction $model)
    {
        parent::__construct($model, $this->databaseTableName);
    }

    /**
     * @param array $queries
     * @return Builder|Model|object|null
     */
    public function findByWhere(array $queries)
    {
        return $this->model::with($this->model->relationships)->where($queries)->sharedLock()->first();
    }
}
