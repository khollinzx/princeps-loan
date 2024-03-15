<?php

namespace App\Traits;

trait EnumToArrayTrait
{

    /**
     * @return array
     */
    public static function keys(): array
    {
        return self::getKeys();
    }

    /**
     * @return array
     */
    public static function values(): array
    {
        return self::getValues();
    }

    /**
     * @return string
     */
    public static function getKeyString(): string
    {
        return implode(',', self::keys());
    }

    /**
     * @return string
     */
    public static function getValueString(): string
    {
        return implode(',', self::values());
    }

    /**
     * @return array
     */
    public static function array(): array
    {
        $types = array_combine(self::values(), self::keys());
        $response = [];
        foreach ($types as $value => $key) {
            $response[] = ['key' => $key, 'value' => $value];
        }
        return $response;
    }

    /**
     *
     * @return array
     */
    public static function KeyValuePairs(): array
    {
        $response = [];
        collect(self::array())->each(function ($element) use (&$response) {
            $response[$element['key']] = ucwords($element['value']);
        });
        return $response;
    }

    /**
     * @return array
     */
    public static function getTypes(): array
    {
        return self::array();
    }
}
