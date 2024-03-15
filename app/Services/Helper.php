<?php


namespace App\Services;

use App\Abstractions\AbstractClasses\BaseRepositoryAbstract;
use App\Models\Agent;
use App\Models\AgentCustomer;
use App\Models\AgentCustomerPivot;
use App\Models\BoonNetwork;
use App\Models\Company;
use App\Models\Merchant;
use App\Models\UserRole;
use App\Utils\Utils;
use Carbon\Carbon;
use Doctrine\DBAL\Query\QueryBuilder;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SendGrid\Mail\TypeException;
use stdClass;

/**
 * Class Helper
 * @package App\Services
 */
class Helper
{

    /**
     * @param BaseRepositoryAbstract $model
     * @param string $column
     * @param string $value
     * @return mixed
     */
    public static function getUserByColumnAndValue(
        BaseRepositoryAbstract $model,
        string $column,
        string $value
    ) {
        return $model->getUserByColumnAndValue($column, $value);
    }

}
