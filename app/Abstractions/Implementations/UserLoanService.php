<?php

namespace App\Abstractions\Implementations;

use App\Abstractions\AbstractClasses\UserModelAbstract;
use App\Models\UserLoan;
use Symfony\Component\HttpKernel\Exception\HttpException;

class UserLoanService extends UserModelAbstract
{

    /**
     * @throws \Exception
     */
    public function applyForLoan(array $validated): ?UserLoan
    {
        $user = $this->getActiveUser();
        ## check if user has existing loan
        if(! $loan) throw new HttpException('you still have a pending loan.');
    }
}
