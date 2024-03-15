<?php

namespace App\Utils;

use App\Abstractions\Interfaces\Transactions\TransactionModelInterface;
use App\Enums\NotificationCategory;
use App\Enums\PaymentModeType;
use App\Enums\RateTypes;
use App\Enums\StudentDataBandwidthCategoryEnum;
use App\Enums\TransactionStatus;
use App\Models\AirtimeRechargeTransaction;
use App\Models\CableTvSubscription;
use App\Models\DataBundleTransaction;
use App\Models\ElectricitySubscription;
use App\Models\Resource;
use App\Services\EmailService;
use App\Services\Helper;
use App\Services\SMS;
use App\Traits\HasPhoneFieldTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class  Utils
{
    use HasPhoneFieldTrait;

    /**
     * @param int $length
     * @return string
     */
    public function randomPassword(int $length = 8): string
    {
        $password   = '';
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $limit      = strlen($characters) - 1;
        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[rand(0, $limit)];
        }

        return $password;
    }

    /**
     * @param int $length
     * @return string
     */
    public static function generateRandomNiN(int $length = 10): string
    {
        $password   = '';
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $limit      = strlen($characters) - 1;
        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[rand(0, $limit)];
        }

        return $password;
    }

    /**
     * @param int $length
     * @return string
     */
    public static function generateRandomNumbers(int $length = 10): string
    {
        $nin   = '';
        $characters = '0123456789';
        $limit      = strlen($characters) - 1;
        for ($i = 0; $i < $length; $i++) {
            $nin .= $characters[rand(0, $limit)];
        }

        return $nin;
    }

    /**
     * @return string
     */
    static function generateTransactionReference(): string
    {
        return mt_rand(100000, 999999) . time();
    }

    /**
     * @return string
     */
    static function generateVendSequence(): string
    {
        return mt_rand(100000, 999999) . time();
    }

    /**
     * @return string
     */
    static function generateFourDigitCode(): string
    {
        return rand(1000, 9999);
    }

    /**
     * This generates the unique treekle Codes
     *
     * @param array $treekleCodes
     * @return string
     */
    public static function generateUniqueTreekleCode(array $treekleCodes): string
    {
        try {
            $start = true;
            $boon_code = '';
            while ($start) {
                $boon_code = random_int(1000000000, 9999999999); # override the existing code
                if (! in_array($boon_code, $treekleCodes)) {
                    $start = false;
                }
            }
            return $boon_code;
        } catch (\Exception $exception) {
            Log::error($exception);
            return '';
        }
    }

    /**
     * This generates the unique phone numbers
     *
     * @param array $pulledNumber
     * @return string
     */
    public static function generateUniquePhoneNumber(array $pulledNumber): string
    {
        try {
            $start = true;
            $phone = '';
            while ($start) {
                $phone = (new self())->getPhoneNumberWithDialingCode('080' . (new self())->generateRandomNumbers(8)); # override the existing code
                if (! in_array($phone, $pulledNumber)) {
                    $start = false;
                }
            }
            return $phone;
        } catch (\Exception $exception) {
            Log::error($exception);
            return '';
        }
    }

    /**
     * @param $query
     * @param array $filters
     * @param array $acceptedFilters
     * @return mixed
     */
    public static function returnFilteredSearchedKeys($query, array $filters, array $acceptedFilters): mixed
    {
        #check that the $key exists in the array of $acceptedFilters
        foreach ($filters as $key => $value)
            if (in_array($key, $acceptedFilters) && $value)
                $query->where($key, 'LIKE', "%$value%");

        return $query;
    }

    /**
     * This saves a model records
     *
     * @param Model $model
     * @param array $records
     * @param bool $returnModel
     * @return Model|void
     */
    public static function saveModelRecord(Model $model, array $records = [], bool $returnModel = true)
    {
        if (count($records)) {
            foreach ($records as $k => $v)
                $model->$k = $v;

            $model->save();
        }

        if($returnModel) return $model;
    }

    /**
     * @param Model $model
     * @param Model $polymorphicModel
     * @param string $polymorphicMethod
     * @param array $records
     * @return Model
     */
    public static function savePolymorphicRecord(Model $model, Model $polymorphicModel, string $polymorphicMethod, array $records): Model
    {
        if (count($records)) {
            foreach ($records as $k => $v) {
                $model->$k = $v;
            }
            $polymorphicModel->$polymorphicMethod()->save($model);
        }
        return $model;
    }

    /**
     * @param $query
     * @param array $filters
     * @return mixed
     */
    public static function getRecordUsingWhereArrays($query, array $filters): mixed
    {
        #check that the $key exists in the array of $acceptedFilters
        foreach ($filters as $column => $value)
            $query->where($column, '=', $value);

        return $query;
    }

    /**
     *
     * @param array $arguments
     * @param string $prefix
     * @return array
     */
    public static function joinPrefixToArrayKeys(array $arguments, string $prefix): array
    {
        try {

            $data = [];

            foreach ($arguments as $key => $value)
                $data = array_merge($data, ["{$prefix}.{$key}" => $value]);

            return $data;

        } catch (\Exception $exception) {
            Log::error($exception);

            return [];
        }
    }

    /**
     *
     * @param array $references
     * @param string|null $prefix
     * @param string|null $ref
     * @return string|null
     */
    public static function generateUniqueReferenceWithOrWithoutPrefix(array $references, ?string $prefix = null, ?string $ref = null): ?string
    {
        $started = true;
        $reference = null;
        try {
            while ($started) {
                $ref = $ref ?? date('Ymd') . str_replace('-', '', Str::uuid()) . '_' . random_int(1000000000, 9999999999);
                # generate the transaction reference e.g. "2022110115:49:15"
                $reference = $prefix ? "{$prefix}{$ref}" : $ref;
                if (! in_array($reference, $references)) $started = false;
            }
            return $reference;
        } catch (\Exception $exception) {
            Log::error($exception);
            return null;
        }
    }

    /**
     * @param string $name
     * @return string
     */
    public static function createServiceClassNameByName(string $name): string
    {
        return Str::of(str_replace(' ', '', ucwords(strtolower($name))))->studly(). "Service";
    }


    /**
     * @param array $phoneNumbers
     * @return array
     */
    public static function filterValidPhoneNumbers(array $phoneNumbers): array
    {
        $self = (new self());
        $valid_phones = [];
        foreach ($phoneNumbers as $number) {
            if ($self->hasValidNumberOfDigits($number)) {
                $valid_phones[] = $self->getPhoneNumberWithDialingCode($number);
            }
        }
        # return only unique phone numbers
        return array_unique($valid_phones);
    }

    /**
     *
     * @param string $customerIdentifier
     * @param array $data
     * @return void
     */
    public static function keepArrayDataOnSession(string $customerIdentifier, array $data): void
    {
        # get or initialize a new array
        $session_data   = Session::get("{$customerIdentifier}") ?? [];
        $session_data[] = $data;

        Session::put($customerIdentifier, $session_data);
    }

    /**
     *
     * @param string $customerIdentifier
     * @param array $data
     * @return void
     */
    public static function keepArrayDataOnCache(string $customerIdentifier, array $data): void
    {
        # get or initialize a new array
        $session_data   = Cache::get("{$customerIdentifier}") ?? [];
        $session_data[] = $data;

        Cache::put($customerIdentifier, $session_data);
    }

    /**
     *
     * @param string $customerIdentifier
     * @param array $data
     * @return void
     */
    public static function keepArrayDataInRedisCache(string $customerIdentifier, array $data): void
    {
        # get or initialize a new array
        $session_data   = json_decode(Redis::get("{$customerIdentifier}"), true) ?? [];
        $session_data[] = $data;

        Redis::set($customerIdentifier, json_encode($session_data));
    }

    /**
     *
     * @param string $customerIdentifier
     * @return void
     */
    public static function flushCacheData(string $customerIdentifier): void
    {
        if (Cache::has("$customerIdentifier")) {
            Cache::forget("$customerIdentifier");
        }
    }

    /**
     *
     * @param string $customerIdentifier
     * @return void
     */
    public static function flushRedisData(string $customerIdentifier): void
    {
        if (Redis::get($customerIdentifier)) {
            Redis::del($customerIdentifier);
        }
    }

    /**
     * @param int $userId
     * @return void
     */
    public static function resetUserCache(int $userId): void
    {
        Cache::forget("agent-{$userId}-eligibility");
        Cache::forget("user-{$userId}-loan-amount");
    }

    /**
     * This returns the percentage deduction of a loan
     *
     * @param float $amountPaid
     * @param float $totalBalanceLeft
     * @return float
     */
    public static function getLoanPercentageDeduction(float $amountPaid, float $totalBalanceLeft): float
    {
        return self::convert_to_2_decimal_places($amountPaid / $totalBalanceLeft);
    }

    /**
     *
     * @param array $dbRecords
     * @param string $prefix
     * @param bool $isSpace
     * @return string|null
     */
    public static function generateSN(array $dbRecords = [], string $prefix = 'TR-', bool $isSpace = false): ?string
    {
        $serial = null;
        $started = true;
        # loop through all records
        while ($started) {
            $serial      = date('dm Y ') . mt_rand(1000, 9999) . " " . mt_rand(1000, 9999);
            if (! in_array($serial, $dbRecords)) {
                $started = false;
            }
        }
        return $prefix . str_replace(' ', $isSpace ? ' ' : '', $serial); # TR-3101-2023-1857-4262
    }

    /**
     *
     * @param float $amount
     * @return float
     */
    public static function convert_to_2_decimal_places(float $amount): float
    {
        return number_format($amount,2, '.', '');
    }

    /**
     *
     * @return array
     * expected
     */
    public static function getArrayOfUnionResultWithoutDuplicates(array $topListArrayElements, array $allArrayElements): array
    {
        return array_unique(array_merge($topListArrayElements, $allArrayElements));
    }

    /**
     *
     * @param string $subject
     * @param string $search
     * @param string $replace
     * @return string expected
     * expected
     */
    public static function searchAndReplace(string $subject, string $search, string $replace): string
    {
        return str_replace($search, $replace, $subject);
    }

    /**
     * Detect the size value (1.1GB, 300MB, etc.) in a given size string.
     *
     * @param string $byte
     * @param string $size
     * @return float|int|null The detected size value (1.7, 100, etc.) or null if not found.
     */
    public static function detectSize(string $byte, string $size): float|int|null
    {
        $BundleSize = explode($byte, $size);
        if (count($BundleSize)) return $BundleSize[0];
    }

    /**
     *
     * @param int $counts
     * @return array
     */
    public static function randomizeThriftSlots(int $counts): array
    {
        $data = [];
        for ($i = 1; $i <= $counts; $i++) {
            $data[] = $i;
        }

        shuffle($data);
        return $data;
    }

    /**
     *
     * @param string $pascalString
     * @return string
     */
    public static function convertPascalCaseToSnakeCase(string $pascalString): string
    {
        $pascalString = strtolower($pascalString);
        return str_replace(' ', '_', preg_replace('/(?<!^)[A-Z]/', '_$0', $pascalString));
    }

    /**
     *
     * @param array $pascalKeyValues
     * @return array
     */
    public static function convertArrayOfPascalCaseKeysToSnakeCase(array $pascalKeyValues): array
    {
        $response = [];
        foreach ($pascalKeyValues as $key => $value) {
            $response[self::convertPascalCaseToSnakeCase($key)] = $value;
        }
        return $response;
    }

}

