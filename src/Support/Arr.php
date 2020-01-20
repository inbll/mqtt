<?php

namespace Inbll\Mqtt\Support;

class Arr
{
    /**
     * Get an item from an array using "dot" notation.
     *
     * @param array $array
     * @param $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(array $array, $key, $default = null)
    {
        if (is_null($key)) {
            return $array;
        }

        if (static::exists($array, $key)) {
            return $array[$key];
        }

        $segments = explode('.', $key);
        foreach ($segments as $segment) {
            if (is_array($array) && static::exists($array, $segment)) {
                $array = $array[$segment];
            } else {
                return $default;
            }
        }

        return $array;
    }

    /**
     * Determine if the given key exists in the provided array.
     *
     * @param array $array
     * @param $key
     * @return bool
     */
    public static function exists(array $array, $key)
    {
        return array_key_exists($key, $array);
    }
}