<?php

namespace Inbll\Mqtt;

use Inbll\Mqtt\Databases\DatabaseManager;
use Inbll\Mqtt\Drivers\Server\ServerDriver;
use Inbll\Mqtt\Drivers\Server\SwooleDriver;
use Inbll\Mqtt\Support\Arr;
use Inbll\Mqtt\Traits\MessageId;
use InvalidArgumentException;

/**
 * Mqtt Server
 *
 * Class Server
 */
class Server
{
    use MessageId;

    const CACHE_CLIENT_IDS = 'client_ids';

    const CACHE_CLIENTS = 'clients';

    /**
     * @var array
     */
    protected $config = [];

    /**
     * 事件闭包
     *
     * @var array
     */
    protected $eventClosures = [];

    /**
     * @var ServerDriver
     */
    protected $driver;

    /**
     * @var array
     */
    protected $drivers = [
        SwooleDriver::DRIVER_NAME => SwooleDriver::class
    ];


    /**
     * Server constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
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
    protected function initDriver(): void
    {
        $driver = (string)$this->getConfig('driver');

        $class = Arr::get($this->drivers, (string)$driver);
        if (!$class) {
            throw new InvalidArgumentException("Mqtt driver {$driver} is not defined.");
        }

        $this->driver = new $class($this);
    }

    /**
     * 初始化驱动
     */
    protected function initDatabase(): void
    {
        DatabaseManager::init((string)$this->getConfig('database'), $this->getConfig('databases'));
    }

    /**
     * 绑定事件
     *
     * @param string $event
     * @param callable $callback
     */
    public function on(string $event, callable $callback): void
    {
        $this->eventClosures[$event] = $callback;
    }

    /**
     * 触发事件
     *
     * @param string $event
     * @param mixed ...$arguments
     * @return mixed
     */
    public function emit(string $event, ...$arguments)
    {
        $callback = Arr::get($this->eventClosures, $event);
        if ($callback) {
            return $callback(...$arguments);
        }
    }

    public function start()
    {
        $this->initDriver();
        $this->initDatabase();
        $this->reset();

        $this->driver->start();
    }

    public function addClient(string $clientId, array $data): void
    {
        DatabaseManager::connection()->keyInsert(self::CACHE_CLIENT_IDS, $clientId);
        DatabaseManager::connection()->insert(self::CACHE_CLIENTS, $clientId, json_encode($data));
    }

    public function delClient(string $clientId): void
    {
        DatabaseManager::connection()->delete(self::CACHE_CLIENT_IDS, $clientId);
        DatabaseManager::connection()->delete(self::CACHE_CLIENTS, $clientId);

        $this->clearMessageId($clientId);
    }

    public function getClient(string $clientId): array
    {
        return (array)DatabaseManager::connection()->find(self::CACHE_CLIENTS, $clientId);
    }

    protected function reset()
    {
        $clientIds = DatabaseManager::connection()->get(self::CACHE_CLIENT_IDS);
    }
}
