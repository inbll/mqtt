<?php

namespace Inbll\Mqtt\Drivers\Server;

use Inbll\Mqtt\Contracts\ServerInterface;
use Inbll\Mqtt\Support\Arr;

/**
 * 服务端驱动父类
 *
 * Class ServerDriver
 */
abstract class ServerInterfaceDriver implements ServerInterface
{
    /**
     * 事件闭包
     *
     * @var array
     */
    protected $eventClosures = [];


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

    /**
     * MQTT的心跳轮询
     */
    abstract protected function keepAlivePoll(): void;
}
