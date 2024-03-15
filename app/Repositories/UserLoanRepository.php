<?php

namespace App\Repositories;

use App\Abstractions\AbstractClasses\BaseRepositoryAbstract;
use App\Models\AgentOtp;
use App\Models\AgentPurse;
use App\Models\Category;
use App\Models\User;
use App\Models\UserLoan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class UserLoanRepository extends BaseRepositoryAbstract
{

    /**
     * @var string
     */
    protected string $databaseTableName = 'user_loans';

    /**
     *
     * @param UserLoan $model
     */
    public function __construct(UserLoan $model)
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

    /**
     * @param User $user
     * @param array $validated
     * @return Model|null
     */
    public function applyForLoan(User $user, array $validated): ?Model
    {
        return $this->createModel([
            'reference' => 'LOAN_'.Str::uuid()->toString(),
            'user_id' => $user->getId(),
            'loan_amount' => $validated['loan_amount'],
            'income' => $validated['income'],
            'status' => 'NEW',
        ]);
    }
}
