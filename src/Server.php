<?php

namespace Inbll\Mqtt;

use Inbll\Mqtt\Contracts\ServerInterface;
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
     * @var ServerInterface
     */
    protected $driver;

    /**
     * Server constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;

        $this->initDriver();
    }

    /**
     * get config
     *
     * @param string $name
     * @param null $default
     * @return mixed
     */
    public function getConfig(string $name, $default = null)
    {
        return Arr::get($this->config, $name, $default);
    }

    /**
     * 初始化驱动
     */
    protected function initDriver()
    {
        $driver = (string)$this->getConfig('driver');
        $config = $this->getConfig('drivers.' . $driver);
        $method = 'create' . Str::studly($driver) . 'Driver';

        if (!$config) {
            throw new InvalidArgumentException("Mqtt driver {$driver} is not defined.");
        }

        if (!method_exists($this, $method)) {
            throw new InvalidArgumentException("Driver [$driver] not supported.");
        }

        $this->driver = $this->$method($config);
    }

    /**
     * 创建Swoole驱动
     *
     * @param array $config
     * @return SwooleDriver
     */
    protected function createSwooleDriver(array $config)
    {
        return new SwooleDriver($this->getConfig('port'), $config);
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
        return $this->driver->$method(...$arguments);
    }
}
