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
    use HasPhoneFieldTrait;

    /**
     * @var Controller
     */
    protected Controller $controller;

    /**
     * @var AgentCustomer
     */
    protected AgentCustomer $agentCustomer;

    /**
     * @var Product
     */
    protected Product $product;

    /**
     * @var NinRestriction
     */
    protected NinRestriction $ninRestriction;

    /**
     * @var Transaction
     */
    protected Transaction $transaction;

    /**
     * @var Agent
     */
    protected Agent $agent;

    /**
     * @var ProductService
     */
    protected ProductService $productService;

    /**
     * @var ThirdPartyProviderService
     */
    protected ThirdPartyProviderService $providerService;

    /**
     * @var ThriftMobileActivityService
     */
    protected ThriftMobileActivityService $thriftMobileActivityService;

    /**
     *
     * @var string
     */
    protected string $pendingTransactionErrorMessage = 'Please try again in 10 mins time, as you have a pending transaction';

    /**
     * BaseFormRequest constructor.
     */
   public function __construct()
   {
       parent::__construct();
       $this->controller = new Controller();
       $this->agentCustomer = new AgentCustomer();
       $this->product = new Product();
       $this->ninRestriction = new NinRestriction();
       $this->transaction = new Transaction();
       $this->agent = new Agent();
       $this->productService = new ProductService();
       $this->providerService = new ThirdPartyProviderService();
       $this->thriftMobileActivityService = new ThriftMobileActivityService();
   }

    /**
     *
     * @return void
     */
    public function handleContentTypeHeaderValidation(): void
    {
        # validate header
        if (! $this->hasHeader('Content-Type') || $this->header('Content-Type') !== 'application/json') {
            throw new HttpResponseException(JsonResponseAPI::errorResponse(
                'Include Content-Type and set the value to: application/json in your header.',
                ResponseAlias::HTTP_BAD_REQUEST
            ));
        }
    }

    /**
     * THis overrides the default throwable failed message in json format
     * @param Validator $validator
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator)
    {
        Log::error("Form validation error", [$validator->errors()]);
        throw new HttpResponseException(
            JsonResponseAPI::errorResponse(
                $validator->errors()->first(),
                JsonResponseAPI::$UNPROCESSABLE_ENTITY,
                "Form Error"
            )
        );
    }

    /**
     *
     * @param string $message
     * @return array
     */
    public function handleNormalPhoneValidation(string $message = 'This phone number is not registered. Please check that the number is correct or Sign up'): array
    {
        return [
            'required',
            'numeric',
            (new ValidPhoneNumberRule)->country(1)->ignore('phone', $this->phone),
            function ($key, $value, $cb) use($message) {
                if (! (new Agent())->checkIfExists($key, $this->getPhoneNumberWithDialingCode($value))) {
                    $cb($message);
                }
            }
        ];
    }

    /**
     *
     * @param string $message
     * @return array
     */
    public function handleRegistrationCheckForPhoneNumberValidation(string $message = 'This phone number is not registered. Please check that the number is correct or Sign up'): array
    {
        return [
            'required',
            'numeric',
            (new ValidPhoneNumberRule)->country(1)->ignore('phone', $this->phone),
            function ($key, $value, $cb) use($message) {
                /** @var Agent $agent */
                $agent = Agent::repo()->findSingleByWhereClause(['phone' => $this->getPhoneNumberWithDialingCode($value)]);
                if ($agent && ! $agent->created_at) return $cb($message);

                # check that the number hasn't been blocked before
                if (AccountBlocking::repo()->searchByPhoneOrEmail($value)) {
                    return $cb("This $key number has already been blocked. Kindly reach out to the support team if you think this is an error");
                }
                if (DeletedAccount::repo()->countDeletedRecordsByPhone($value) >= DeletedAccountRepository::$STANDARD_DELETION_COUNT) {
                    # block the said account with the email inclusive
                    if ($this->email) {
                        AccountBlocking::repo()->blockAccount(true, $value, $this->email, 'Multiple times account deletion was detected');
                    }
                    return $cb("Your $key number has been blocked, kindly reach out to support for assistance");
                }
            }
        ];
    }

    /**
     *
     * @param int $maxString
     * @return string
     */
    public function handleNameValidation(int $maxString = 180): string
    {
        return "required|string|regex:/^[a-zA-Z\s]+$/u|max:$maxString";
    }

    /**
     * @return array
     */
    public function validateBoonCode(): array
    {
        return [
            'required',
            'nullable',
            function($key, $value, $callback) {
                $agent = $this->agent->fetchAgentByBoonCode($value);
                if (! $agent) return $callback("Invalid Treekle code entered.");
                # check for withdrawal restrictions
                return $this->processAgentProfileValidation($this->agent::find($agent->id), $callback);
            }
        ];
    }

    /**
     * @param Agent $agent
     * @param $callback
     * @return mixed
     */
    public function processAgentProfileValidation(Agent $agent, $callback): mixed
    {
        # check for withdrawal restrictions
        if (! $agent->hasCompletedProfiling()) {
            $total_vending_cap = $this->ninRestriction->getVendingLimit(true);

            if (! $total_vending_cap) return $callback("The Agent with the entered Treekle code is restricted from Vending. No NIN Profiling.");
            $total_vended = Transaction::repo()->getTotalVendingByAgent($agent) + (float) $this->amount;
            # validate vending based on NIN availability
            if ($total_vended > $total_vending_cap) return $callback("The Agent with the entered Treekle code is restricted to a total Vending of NGN$total_vending_cap. No NIN Profiling.");
        }
    }

    /**
     * @return array
     */
    public function validateBillsClassProductForCableTv(): array
    {
        return [
            'required',
            'exists:products,class_name',
            function($k, $v, $f) {
                /** @var Product $product */
                $product = $this->product->findProductByWhere(['class_name' => $v, 'category_id' => 2]);
                if (! $product) return $f("Invalid product selected.");
                # check the account balance of the Third party activated.
                $provider = $this->providerService->initialize($product->getServiceId())->getThirdPartyServiceProvider();
                # check account balance
                if (! $provider->isBalanceSufficient(VirtualTopUp::getLatestRecord(), $this->amount ?? 0)) return $f("Sorry, we could not process your request at this time.");
                if (! $product->isActive()) return $f("The selected product is inactive.");

                # check that the User does not have a pending transaction with the same payload
                if (isset($this->smartCardNumber) && isset($this->subscriptionCode)) {
                    if (CableTvSubscription::repo()->checkIfCableTvPayloadAlreadyExistsAsPendingTransaction(
                        $this->controller->getUser(),
                        $this->smartCardNumber,
                        $this->subscriptionCode,
                        $this->amount,
                        $v,
                    )
                    ) { return $f($this->pendingTransactionErrorMessage); }
                }
            }
        ];
    }

    /**
     *
     * @param bool $phoneNotFormatted
     * @param string $treekleCode
     * @return array
     */
    public function validateCustomerPhone(bool $phoneNotFormatted = false, string $treekleCode = ''): array
    {
        return [
            'required',
            (new ValidPhoneNumberRule)->country(1)->ignore('phone', $this->phone),
            function($k, $v, $f) use($phoneNotFormatted, $treekleCode) {
                # Validate OFFNET Vending of Airtime
                if ($this->direction === Transaction::OFFNET) {
                    # check that the Number is registered in the AgentCustomer model irrespective of the direction [OFFNET or DIRECT]
                    $customer    = $this->agentCustomer->findByPhoneAndTreekleCode(!$phoneNotFormatted? "234" . (int)$v : $v, $treekleCode, true);
                    if ($treekleCode && !$customer) return $f("You're not linked to this Treekle code.");

                    $offnet_user = (new OffnetUser())->getAgentByBoonCodeAndCustomerNumber("234" . (int)$v, $this->boonCode);
                    if (!$offnet_user) return $f("Your phone number has not been tied to the provided Treekle Code.");

                    # check if account is disabled
                    if (!$offnet_user->is_enabled) return $f("Your account is currently disabled by your Benefactor.");

                    # check if limit has been reached
                    if ($offnet_user->reached_limit) return $f("You have reached your limit. Kindly contact your benefactor.");

                    if (((float)$this->amount + (float)$offnet_user->temporary_cumulative_limit_amount) > $offnet_user->recharge_limit)
                        return $f("The amount you're trying to vend has exceeded your limit.");
                }
            }
        ];
    }

    /**
     * @return string[]
     */
    public function validateAmountToPay(): array
    {
        return [
            'required',
            'numeric',
            'min:50',
            // 'max:30000',
        ];
    }

    /**
     * @return array
     */
    public function validateSmartCard(): array
    {
        return [
            'required',
            'numeric',
            function ($k, $v, $cb) {
                if (! count(CableTvCache::getCachedDecoderValidationResponse($v))) {
                    return $cb("Unable to validate your decoder number");
                }
            }
        ];
    }

    /**
     * @return array
     */
    public function validateSubscriptionCode(): array
    {
        return [
            'required',
            'string',
            function ($k, $v, $cb) {
                if (! CableTvCache::isPackageProductCodeAvailable($this->billsClassName, $v)) {
                    return $cb("Invalid bouquet code selected");
                }
            }
        ];
    }

    /**
     * @return array
     */
    public function validateAmount(): array
    {
        return [
            'required',
            'numeric',
            'min:50',
            function($key, $value, $fn) {
                # check for NIN restriction
                if (! auth()->user()->completed_profiling) {
                    $total_vending_cap = $this->ninRestriction->getVendingLimit(true);
                    if (! $total_vending_cap)
                        return $fn("You are restricted from making a direct vending at the moment. Kindly update your NIN to remove this restriction.");

                    $total_vended = Transaction::repo()->getTotalVendingByAgent($this->controller->getUser()) + (float) $value;
                    # validate vending based on NIN availability
                    if ($total_vended > $total_vending_cap)
                        return $fn("You are restricted to a total vending of NGN$total_vending_cap. Kindly update your NIN to remove this restriction.");
                }
            }
        ];
    }

    /**
     * @return array
     */
    public function validateCustomerPhoneAgainstDataVending(): array
    {
        return [
            'required',
            (new ValidPhoneNumberRule)->country()->ignore('phone', $this->customerPhone),
            function ($k, $v, $cb) {
                # check that the User does not have a pending transaction of the same parameters $phone $amount
                /** @var Product $product */
                $product = Session::get('classProduct');
                if (isset($this->amount) && isset($this->customerPhone) && $product && isset($this->productId)) {
                    if (DataBundleTransaction::repo()->checkIfBundlePayloadAlreadyExistsAsPendingTransaction(
                        $this->controller->getUser(),
                        $v,
                        $this->amount,
                        $this->productId,
                        $product->getName(),
                    )) {
                        return $cb($this->pendingTransactionErrorMessage);
                    }
                    Session::forget('classProduct');
                }
            }
        ];
    }

    /**
     * @return array
     */
    public function validateDataBundleProductId(): array
    {
        return [
            'required',
            'string',
            function ($k, $v, $cb) {
                if (! DataBundleCache::isBundleCodeAvailable($this->providerClassName, $v)) {
                    return $cb("Invalid product code selected");
                }
            }
        ];
    }

    /**
     * @return array
     */
    public function validateTokenCode(): array
    {
        return [
            'required',
            'string',
            function ($k, $v, $fn) {
                /** @var PromoUsage $promo_usage */
                $promo_usage = PromoUsage::repo()->findSingleByWhereClause(['code' => $v]);
                if (! $promo_usage || ! $promo_usage->isValid()) {
                    return $fn("Invalid Token code entered.");
                }
            }
        ];
    }

    /**
     *
     * @param bool $isAirtime
     * @param int|null $selectedThirdPartyServiceId
     * @return array
     */
    public function validateNetworkServiceProviderClassName(bool $isAirtime = false, ?int $selectedThirdPartyServiceId = null): array
    {
        return [
            "required",
            "string",
//            Rule::exists("products", 'class_name')->where('active', 1)->where('category_id', 1),
            function ($k, $v, $fn) use($selectedThirdPartyServiceId, $isAirtime) {
                $product  = $this->product->findProductByWhere(['class_name' => $v, 'category_id' => $isAirtime ? 1: 4]);
                if (! $product) return $fn("Invalid provider selected.");
                # check the account balance of the Third party activated.
                $provider = $this->providerService->initialize($product->getServiceId())->getThirdPartyServiceProvider();
                # check account balance
                if (! $provider->isBalanceSufficient(VirtualTopUp::getLatestRecord(), $this->amount ?? 0)) return $fn("Sorry, we could not process your request at this time.");
                # check that the $selectedThirdPartyServiceId is the same with the currently set provider on the product
                # this is valid especially when a Customer is vending and a Product provider was changed in the process.
                if ($selectedThirdPartyServiceId && $product->getServiceId() !== $selectedThirdPartyServiceId) return $fn("SERVICE_PROVIDER_CHANGED");
                if (! in_array($product->getCategoryId(), [1, 4])) return $fn("Select a Network service provider");
                if (! $product->isActive()) return $fn("The Service provider selected is not active at the moment.");
                # validate the amount for
                if (isset($this->amount) && App::environment(['staging', 'development'])) {
                    if ((float)$this->amount < 100) return $fn("You can only vend a minimum of NGN1000.");
                    if ($v === 'MTNService' && (float)$this->amount > 1000) return $fn("You can only vend a maximum of NGN1000.");
                }
            }
        ];
    }

    /**
     *
     * @param int|null $categoryId
     * @param int|null $serviceProviderId
     * @return array
     */
    public function validateProviderClassName(?int $categoryId = null, ?int $serviceProviderId = null): array
    {
        return [
            "required",
            "string",
            function ($k, $v, $fn) use($categoryId, $serviceProviderId) {
                $wheres = ['class_name' => $v, 'active' => 1];
                if ($categoryId) $wheres['category_id'] = $categoryId;
                /** @var Product $product */
                $product = $this->product->findProductByWhere($wheres);
                if (! $product) return $fn("Invalid product selected.");
                Session::put('classProduct', $product);

                # get the active Service Provider
                $provider = $this->providerService->initialize($product->getServiceId())->getThirdPartyServiceProvider();
                $amount = $this->amount ?? Session::get('AIRTIME_LOAN_AMOUNT') ?? 0;

                # check that the amount is vendable by the service provider
                if ($product->getService()->hasExceededVendingLimit($amount)) {
                    return $fn("Amount exceeded. Kindly enter below: {$product->getService()->getVendingLimit()}");
                }

                # check account balance
                if (! $provider->isBalanceSufficient(VirtualTopUp::getLatestRecord(), $amount)) {
                    return $fn("Sorry, we could not process your request at this time.");
                }

                if ($serviceProviderId && $product->getServiceId() !== $serviceProviderId) {
                    return $fn("SERVICE_PROVIDER_CHANGED");
                }
            }
        ];
    }

    /**
     *
     * @param int|null $categoryId
     * @param int|null $serviceProviderId
     * @return array
     */
    public function validateProviderClassNameOnStudentData(?int $categoryId = null, ?int $serviceProviderId = null): array
    {
        return [
            "required",
            "string",
            function ($k, $v, $fn) use($categoryId, $serviceProviderId) {
                $wheres   = ['class_name' => $v, 'active' => 1];
                if ($categoryId) $wheres['category_id'] = $categoryId;
                $product  = $this->product->findProductByWhere($wheres);
                if (! $product) return $fn("Invalid product selected.");
                if ($serviceProviderId && $product->getServiceId() !== $serviceProviderId) return $fn("SERVICE_PROVIDER_CHANGED");
                Session::put('classProduct', $product);
            }
        ];
    }

    /**
     * @return array
     */
    public function handleStudentDataVendingProductId(): array
    {
        return [
            'required',
            'string',
            function($k, $v, $fn) {
                /** @var Agent $user */
                $user = Auth::user();
                $bundles = json_decode(Redis::get("student-data-bundles-" . get_class($user) . "-{$user->getId()}"), true);
                if (! $bundles || ! count($bundles)) return $fn("You have not selected any bundle.");
                $bundle = Helper::getSelectedBundlesFromArrayOfBundles($bundles, $v);
                if (! count($bundle)) return $fn("Invalid bundle package selected.");
                // check the amount from the further discount
                $discounts = json_decode(Redis::get("student-data-further-discounts-" . get_class($user) . "-{$user->getId()}"), true);
                // get the updated price from the tasks if available
                $amount = $discounts['newPrice'] ?? $bundle['price'];
                if ($this->providerClassName === "MTNService") {
                    /** @var StudentDataMTNNetworkSwitch $product */
                    $product = StudentDataMTNNetworkSwitch::repo()->getConfig();
                    Session::put("STD_SERVICE", $product->getService()->getName());
                } else {
                    /** @var Product $product */
                    $product = Product::repo()->findSingleByWhereClause(['class_name' => $this->providerClassName]);
                }
                Log::alert('Selected Product', [$product]);
                if (! $product) return $fn("Invalid product selected.");
                # get the active Service Provider
                $provider = $this->providerService->initialize($product->getServiceId())->getThirdPartyServiceProvider();

                Log::alert('Selected Provider', [$provider]);
                # check account balance
                if (! $provider->isBalanceSufficient(VirtualTopUp::getLatestRecord(), $amount)) return $fn("Sorry, we could not process your request at this time.");

                if (! $user->hasCompletedProfiling()) {
                    $total_vending_cap = $this->ninRestriction->getVendingLimit(true);
                    if (! $total_vending_cap) {
                        return $fn("You are restricted from making a direct vending at the moment. Kindly update your NIN to remove this restriction.");
                    }
                    $total_vended = Transaction::repo()->getTotalVendingByAgent($this->controller->getUser()) + (float) $amount;
                    # validate vending based on NIN availability
                    if ($total_vended > $total_vending_cap) {
                        return $fn("You are restricted to a total vending of NGN$total_vending_cap. Kindly update your NIN to remove this restriction.");
                    }
                }

                /** @var Product $product */
                $product = Session::get('classProduct');
                # check that the User does not have a pending transaction with the same payload
                if (isset($this->customerPhone) && $product && isset($this->productId)) {
                    if (DataBundleTransaction::repo()->checkIfBundlePayloadAlreadyExistsAsPendingTransaction(
                        $this->controller->getUser(),
                        $this->customerPhone,
                        $amount,
                        $v,
                        $product->getName(),
                    )) {
                        return $fn($this->pendingTransactionErrorMessage);
                    }
                    Session::forget('classProduct');
                }
            }
        ];
    }

    /**
     *
     * @param int $categoryId
     * @param int|null $serviceProviderId
     * @return array
     */
    public function validateNetworkProviderClassName(int $categoryId = 1, ?int $serviceProviderId = null): array
    {
        return [
            "required",
            "string",
            function ($k, $v, $fn) use($categoryId, $serviceProviderId) {
                $wheres   = ['class_name' => $v, 'active' => 1];
                if ($categoryId && $serviceProviderId) $wheres['category_id'] = $categoryId;
                /** @var Product $product */
                $product  = $this->product->findProductByWhere($wheres);
                if (! $product) return $fn("Invalid product selected.");
                # check the account balance of the Third party activated.
                $provider = $this->providerService->initialize($product->getServiceId())->getThirdPartyServiceProvider();
                # check that the amount is vendable by the service provider
                if ($product->getService()->hasExceededVendingLimit($this->amount ?? 0)) {
                    return $fn("Amount exceeded. Kindly enter below: {$product->getService()->getVendingLimit()}");
                }
                # check account balance
                if (! $provider->isBalanceSufficient(VirtualTopUp::getLatestRecord(), $this->amount ?? 0)) return $fn("Sorry, we could not process your request at this time.");
                if ($serviceProviderId && $product->getServiceId() !== $serviceProviderId) return $fn("SERVICE_PROVIDER_CHANGED");
            }
        ];
    }

    /**
     * @return array
     */
    public function validateElectricityMeterNumber(): array
    {
        return [
            'required',
            'string',
            function ($k, $v, $cb) {
                if (! count(ElectricityCacheService::getCachedMeterValidationResponse($v))) {
                    return $cb("Unable to validate your Meter number");
                }
            }
        ];
    }

    /**
     * @return array
     */
    public function validateElectricityProductClass(): array
    {
        return [
            'required',
            'exists:products,class_name',
            function($k, $v, $f) {
                /** @var Product $product */
                $product  = $this->product->findProductByWhere(['class_name' => $v, 'category_id' => 3]);
                if (! $product->isActive()) return $f("The selected product is inactive.");
                # check the account balance of the Third party activated.
                $provider = $this->providerService->initialize($product->getServiceId())->getThirdPartyServiceProvider();
                # check account balance
                if (! $provider->isBalanceSufficient(VirtualTopUp::getLatestRecord(), $this->amount ?? 0)) {
                    return $f("Sorry, we could not process your request at this time.");
                }
                # check that the User does not have a pending transaction of the same parameters
                if (isset($this->customerPhone) && isset($this->meterNo) && isset($this->service)) {
                    if (ElectricitySubscription::repo()->checkIfElectricityPayloadAlreadyExistsAsPendingTransaction(
                        $this->controller->getUser(),
                        $this->meterNo,
                        $this->customerPhone,
                        $this->amount,
                        $this->service
                    )) {
                        return $f($this->pendingTransactionErrorMessage);
                    }
                }
            }
        ];
    }

    /**
     * @return string[]
     */
    public function validateStudentPin(): array
    {
        return [
            'required',
            'digits:4',
            function ($k, $v, $cb) {
                if (! auth()->user()->pin) return $cb("PIN_NOT_SET");
                # check the PIN
                if ($v !== auth()->user()->pin) return $cb("You have entered an incorrect transaction PIN");
            }
        ];
    }

    /**
     * @return array
     */
    public function messages(): array
    {
        return CustomError::customErrorMessages();
    }
}
