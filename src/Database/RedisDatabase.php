<?php

namespace Inbll\Mqtt\Database;

use Inbll\Mqtt\Support\Arr;
use Redis;
use Inbll\Mqtt\Contracts\DatabaseInterface;

/**
 * Class RedisDatabase
 * @package Inbll\Mqtt\Database
 */
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


    /**
     * RedisDatabase constructor.
     * @param array $config
     */
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
        ini_set('default_socket_timeout', -1);

        $this->redis = new Redis();
        $this->redis->connect($this->getConfig('host'), $this->getConfig('port'), $this->getConfig('timeout', 0));
        $this->redis->setOption(Redis::OPT_READ_TIMEOUT, -1);

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

    /**
     * set type implementation
     *
     * @param string $table
     * @param string $key
     */
    public function keyInsert(string $table, string $key): void
    {
        $this->redis->sAdd($table, $key);
    }

    /**
     * hash type implementation
     *
     * @param string $table
     * @param string $key
     * @param array $data
     * @return bool
     */
    public function insert(string $table, string $key, array $data): bool
    {
        return (bool)$this->redis->hSet($table, $key, json_encode($data));
    }

    /**
     * hash type implementation
     *
     * @param string $table
     * @param string $key
     * @param array $data
     * @return bool
     */
    public function update(string $table, string $key, array $data): bool
    {
        $oldData = $this->find($table, $key) ?: [];
        $data = array_merge($oldData, $data);
        
        return (bool)$this->redis->hSet($table, $key, json_encode($data));
    }

    /**
     * hash|set type implementation
     *
     * @param string $table
     * @param bool $serialize
     * @return array
     */
    public function get(string $table, bool $serialize = true): array
    {
        if ($this->redis->exists($table) == false) {
            return [];
        }

        if ($this->redis->type($table) == Redis::REDIS_HASH) {
            $data = $this->redis->hGetAll($table);
        } else {
            $data = $this->redis->sMembers($table);
        }

        $data = $data ? $data : [];
        if ($serialize) {
            foreach ($data as $k => $v) {
                $data[$k] = json_decode($v, true);
            }
        }

        return $data;
    }

    /**
     * hash type implementation
     *
     * @param string $table
     * @param string $key
     * @return array|null
     */
    public function find(string $table, string $key): ?array
    {
        if ($this->redis->type($table) != Redis::REDIS_HASH) {
            return null;
        }

        return json_decode($this->redis->hGet($table, $key), true) ?: null;
    }

    /**
     * hash type implementation
     *
     * @param string $table
     * @param string $key
     * @param string $field
     * @return mixed|null
     */
    public function value(string $table, string $key, string $field)
    {
        if ($this->redis->type($table) != Redis::REDIS_HASH) {
            return null;
        }

        $data = $this->find($table, $key);

        return ($data && array_key_exists($field, $data)) ? $data[$field] : null;
    }

    /**
     * hash|set type implementation
     *
     * @param string $table
     * @param string $key
     */
    public function delete(string $table, string $key): void
    {
        if ($this->redis->type($table) == Redis::REDIS_HASH) {
            $this->redis->hDel($table, $key);
        } else {
            $this->redis->sRem($table, $key);
        }
    }

    /**
     * hash|set type implementation
     *
     * @param string $table
     * @param string $key
     * @return bool
     */
    public function exists(string $table, string $key): bool
    {
        if ($this->redis->type($table) == Redis::REDIS_HASH) {
            return $this->redis->hExists($table, $key);
        } else {
            return $this->redis->sIsMember($table, $key);
        }
    }

    /**
     * hash|set type implementation
     *
     * @param string $table
     * @return int
     */
    public function count(string $table): int
    {
        if ($this->redis->type($table) == Redis::REDIS_HASH) {
            return (int)$this->redis->hLen($table);
        } else {
            return (int)$this->redis->sCard($table);
        }
    }

    public function truncate(string $table): void
    {
        $this->redis->del($table);
    }
}