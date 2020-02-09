<?php

namespace Inbll\Mqtt\Database;

use Inbll\Mqtt\Contracts\DatabaseInterface;
use Inbll\Mqtt\Support\Arr;

/**
 *
 *
 * Class DatabaseManager
 * @package Inbll\Mqtt\Database
 */
class DatabaseManager
{
    /**
     * @var array
     */
    protected static $databases = [
        'redis' => RedisDatabase::class,
    ];

    /**
     * @var string
     */
    protected static $defaultDatabase;

    /**
     * @var array
     */
    protected static $configs;

    /**
     * @var DatabaseInterface
     */
    protected static $connection;

    /**
     * 初始化数据库
     *
     * @param string $defaultDatabase
     * @param array $configs
     */
    public static function init(string $defaultDatabase, array $configs): void
    {
        static::$defaultDatabase = $defaultDatabase;
        static::$configs = $configs;

        $config = Arr::get(static::$configs, static::$defaultDatabase);
        $class = Arr::get(static::$databases, $defaultDatabase);
        if (!$class || !$config) {
            throw new \InvalidArgumentException("Mqtt database {$defaultDatabase} is not defined.");
        }

        static::$connection = new $class($config);
    }

    /**
     * 返回连接
     *
     * @return DatabaseInterface
     */
    public static function connection(): DatabaseInterface
    {
        return static::$connection;
    }
}