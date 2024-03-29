<?php

namespace App\Repositories;

use App\Abstractions\AbstractClasses\BaseRepositoryAbstract;
use App\Models\AgentOtp;
use App\Models\AgentPurse;
use App\Models\State;
use App\Models\User;
use App\Models\UserLoanRepayment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class UserLoanRepaymentRepository extends BaseRepositoryAbstract
{

    /**
     * @var string
     */
    protected string $databaseTableName = 'user_loan_repayments';

    /**
     *
     * @param UserLoanRepayment $model
     */
    public function __construct(UserLoanRepayment $model)
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
