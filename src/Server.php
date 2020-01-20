<?php

namespace Inbll\Mqtt;

use Inbll\Mqtt\Drivers\Server\ServerInterfaceDriver;
use Inbll\Mqtt\Drivers\Server\SwooleDriver;
use Inbll\Mqtt\Support\Arr;
use Inbll\Mqtt\Support\Str;
use InvalidArgumentException;

/**
 * Mqtt Server
 *
 * Class Server
 *
 * @method void on(string $event, callable $callback)
 * @method void start()
 */
class Server
{
    /**
     * @var array
     */
    protected $config = [];

    /**
     * @var ServerInterfaceDriver[]
     */
    protected $drivers = [];


    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function getConfig(string $name, $default = null)
    {
        return Arr::get($this->config, $name, $default);
    }

    /**
     * 获取默认驱动
     *
     * @return string
     */
    public function getDefaultDriver()
    {
        return $this->getConfig('default');
    }

    /**
     * 获取驱动
     *
     * @param string|null $name
     * @return ServerInterfaceDriver
     */
    public function driver(string $name = null): ServerInterfaceDriver
    {
        $driver = $name ?? $this->getDefaultDriver();

        return $this->drivers[$driver] ?? $this->resolve($driver);
    }

    /**
     * 创建驱动实例
     *
     * @param string $driver
     * @return ServerInterfaceDriver
     */
    protected function resolve(string $driver): ServerInterfaceDriver
    {
        $config = $this->getConfig('drivers.' . $driver);
        if (is_null($config)) {
            throw new InvalidArgumentException("Mqtt driver {$driver} is not defined.");
        }

        if (!isset($this->customCreators[$driver])) {
            $method = 'create' . Str::studly($driver) . 'Driver';

            if (!method_exists($this, $method)) {
                throw new InvalidArgumentException("Driver [$driver] not supported.");
            }

            $this->drivers[$driver] = $this->$method($config);
        }

        return $this->drivers[$driver];
    }

    /**
     * 创建Swoole驱动
     *
     * @param array $config
     * @return SwooleDriver
     */
    protected function createSwooleDriver(array $config)
    {
        return new SwooleDriver($this->getConfig('redis_channel'), $this->getConfig('port'), $config);
    }

    /**
     * 魔术方法直接调用驱动方法
     *
     * @param $method
     * @param $arguments
     * @return mixed
     */
    public function __call($method, $arguments)
    {
        return $this->driver()->$method(...$arguments);
    }
}
