<?php

namespace Inbll\Mqtt\Databases;

use Inbll\Mqtt\Support\Arr;
use Redis;
use Inbll\Mqtt\Contracts\DatabaseInterface;

class RedisDatabase implements DatabaseInterface
{
    /**
     * @var array
     */
    protected $config;

    /**
     * @var Redis
     */
    protected $redis;


    public function __construct(array $config)
    {
        $this->config = $config;

        $this->init();
    }

    /**
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function getConfig(string $key, $default = null)
    {
        return Arr::get($this->config, $key, $default);
    }

    public function init()
    {
        $this->redis = new Redis();
        $this->redis->connect($this->getConfig('host'), $this->getConfig('port'), $this->getConfig('timeout', 0));

        // set password
        if ($password = $this->getConfig('password')) {
            $this->redis->auth($password);
        }

        // set key prefix
        if ($keyPrefix = $this->getConfig('prefix')) {
            $this->redis->setOption(Redis::OPT_PREFIX, $keyPrefix);
        }

        // set db
        if ($database = $this->getConfig('database')) {
            $this->redis->select($database);
        }
    }

    public function keyInsert(string $table, $key): void
    {
        $this->redis->sAdd($table, $key);
    }

    public function insert(string $table, $key, $value): void
    {
        $this->redis->hSet($table, $key, $value);
    }

    public function get(string $table): array
    {
        if ($this->redis->type($table) == Redis::REDIS_HASH) {
            return $this->redis->hGetAll($table);
        } else {
            return $this->redis->sMembers($table);
        }
    }

    public function find(string $table, $key)
    {
        if ($this->redis->type($table) == Redis::REDIS_HASH) {
            return $this->redis->hGet($table, $key);
        }
    }

    public function delete(string $table, $key): void
    {
        if ($this->redis->type($table) == Redis::REDIS_HASH) {
            $this->redis->hDel($table, $key);
        } else {
            $this->redis->sRem($table, $key);
        }
    }

    public function exists(string $table, $key): bool
    {
        if ($this->redis->type($table) == Redis::REDIS_HASH) {
            return $this->redis->hExists($table, $key);
        } else {
            return $this->redis->sIsMember($table, $key);
        }
    }

    public function truncate(string $table): void
    {
        $this->redis->del($table);
    }
}