# mqtt broker

基于swoole实现的mqtt broker，不建议在正式环境中使用。

# 未实现
1. publish消息的保留以及未来的订阅分发（RETAIN）
2. 主题订阅分发的效率不行
3. 未压测

## 要求

PHP >= ^7.1

ext-swoole >= ^4.0

ext-redis

## install

```shell
composer require "inbll/mqtt"
```

```demo
<?php

$config = [
    'database' => 'redis',

    /**
     * protocol version in 3.1、3.1.1
     */
    'version' => '3.1.1',

    /**
     * port
     */
    'port' => 1883,

    /**
     * swoole config
     */
    'swoole' => [
        'worker_num' => 2,

        'task_worker_num' => 2,

        'task_enable_coroutine' => true,

        'heartbeat_check_interval' => 10,
        'heartbeat_idle_time' => 120,
    ],

    /**
     * database configs
     */
    'databases' => [
        'redis' => [
            'host' => '127.0.0.1',
            'password' => null,
            'port' => 6379,
            'database' => 0,
            'prefix' => 'mqtt_'
        ]
    ],
];

$broker = new Broker($config);

$broker->on('start', function (Broker $broker) {
    $this->info('start...');
});

$broker->on('connected', function (Broker $broker, string $clientId) {
    $this->info('hello ' . $clientId);
});

$broker->on('message', function (Broker $broker, string $clientId, string $topic, string $message) {
    $broker->publish($clientId, 'test', 'hello', 2);
    
    $broker->close($clientId);
});

$broker->start();
```
