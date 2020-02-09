<?php

namespace Inbll\Mqtt\Database\Models;

use Inbll\Mqtt\Contracts\DatabaseInterface;
use Inbll\Mqtt\Database\DatabaseManager;

/**
 * Class Model
 * @package Inbll\Mqtt\Database\Models
 */
abstract class Model
{
    protected $table;

    protected static $instances = [];


    /**
     * instance object
     *
     * @return static
     */
    public static function instance(): self
    {
        $class = get_called_class();
        if (!array_key_exists($class, static::$instances)) {
            static::$instances[$class] = new static();
        }

        return static::$instances[$class];
    }

    /**
     * @return DatabaseInterface
     */
    protected function db(): DatabaseInterface
    {
        return DatabaseManager::connection();
    }
}