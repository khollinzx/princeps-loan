<?php

namespace App\Traits;

use App\Models\Country;
use InvalidArgumentException;

trait HasPhoneFieldTrait
{

    /**
     * @var Country
     */
    protected Country $country;

    public function __construct() {}

    /**
     * @return Country
     */
    protected function getCountryField(): Country
    {
        return Country::where('id', 1)->sharedLock()->first();
    }

    /**
     *
     * @param string|int|null $phoneNumber
     * @param string $fieldName
     * @return string
     */
    protected function getPhoneNumberField(string|int $phoneNumber = null, string $fieldName = 'phone'): string
    {
        if (is_null($phoneNumber) && is_null($this->{$fieldName}))
            throw new InvalidArgumentException('No phone field found!');

        if (is_null($phoneNumber) && !is_null($this->{$fieldName}))
            $phoneNumber = $this->phone;

        return $phoneNumber;
    }

    /**
     *
     * @param string|int|null $phoneNumber
     * @return string
     */
    public function getPhoneNumberWithoutDialingCode(string|int $phoneNumber = null): string
    {
        $country = $this->getCountryField();
        $phoneNumber = $this->getPhoneNumberField($phoneNumber);

        if (preg_match("/^\+?{$country->getDialingCode()}[0-9]+$/", $phoneNumber))
            $phoneNumber = preg_replace("/^\+?{$country->getDialingCode()}/", 0, $phoneNumber);

        return $phoneNumber;
    }

    /**
     *
     * @param string|int $phoneNumber
     * @return string
     */
    public function getPhoneNumberWithDialingCode(string|int $phoneNumber): string
    {
        return $this->getCountryField()->getDialingCode() . (int) $this->getPhoneNumberWithoutDialingCode($phoneNumber);
    }

    /**
     *
     * @param string|int $phoneNumber
     * @return bool
     */
    public function hasValidNumberOfDigits(string|int $phoneNumber): bool
    {
        return strlen($this->getPhoneNumberWithoutDialingCode($phoneNumber)) === $this->getCountryField()->getTotalDigits();
    }
}
