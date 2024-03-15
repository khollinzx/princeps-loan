<?php

namespace App\Http\Requests;

use App\Abstractions\Implementations\Thrifts\ThriftMobileActivityService;
use App\Http\Controllers\Controller;
use App\Models\AccountBlocking;
use App\Models\Agent;
use App\Models\AgentCustomer;
use App\Models\CableTvSubscription;
use App\Models\DataBundleTransaction;
use App\Models\DeletedAccount;
use App\Models\ElectricitySubscription;
use App\Models\NinRestriction;
use App\Models\OffnetUser;
use App\Models\Product;
use App\Models\PromoUsage;
use App\Models\StudentDataMTNNetworkSwitch;
use App\Models\Transaction;
use App\Models\VirtualTopUp;
use App\Repositories\AccountBlockingRepository;
use App\Repositories\DeletedAccountRepository;
use App\Rules\ValidPhoneNumberRule;
use App\Services\Caches\Vendings\CableTvCache;
use App\Services\Caches\Vendings\DataBundleCache;
use App\Services\Caches\Vendings\ElectricityCacheService;
use App\Services\CustomError;
use App\Services\Helper;
use App\Services\JsonResponseAPI;
use App\Services\ProductService;
use App\Services\ThirdPartyProviderService;
use App\Traits\HasPhoneFieldTrait;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response as ResponseAlias;

class BaseFormRequest extends FormRequest
{
    /**
     * BaseRequest constructor.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * This overrides the default throwable failed message in json format
     * @param Validator $validator
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(JsonResponseAPI::errorResponse($validator->errors()->first(), JsonResponseAPI::$BAD_REQUEST));
    }
}
