<?php

namespace Inbll\Mqtt\Drivers\Server;

use Inbll\Mqtt\Contracts\ServerInterface;
use Inbll\Mqtt\Databases\DatabaseManager;
use Inbll\Mqtt\Server;
use Inbll\Mqtt\Support\Arr;

/**
 * 服务端驱动父类
 *
 * Class ServerDriver
 */
abstract class ServerDriver implements ServerInterface
{
    const DRIVER_NAME = '';

    const CACHE_CLIENT_IDS = 'client_ids';


    /**
     * 驱动配置
     *
     * @var array
     */
    protected $config;

    protected $manager;


    public function __construct(Server $manager)
    {
        $this->manager = $manager;

        $this->config = $manager->getConfig('drivers.' . self::DRIVER_NAME, []);
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function getConfig(string $key)
    {
        return Arr::get($this->config, $key);
    }


    /**
     * MQTT的心跳轮询
     */
    abstract protected function keepAlivePoll(): void;
}
