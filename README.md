# Mqtt broker

Based on [swoole](https://github.com/swoole/swoole-src) on mqtt broker,not recommended for use in a product environment.

# Mqtt Version
3.1、3.1.1

# Problem
1. Publish retain;
2. Topic subscription allocation is not efficient；
3. Not tested。

## Require

PHP >= ^7.1

ext-swoole >= ^4.0

ext-redis

## Install

```shell
composer require "inbll/mqtt"
```

```demo
<?php

use Inbll\Mqtt\Broker;


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
    $broker->log('start...');
});

$broker->on('connected', function (Broker $broker, string $clientId) {
    $$broker->log('hello ' . $clientId);
});

$broker->on('message', function (Broker $broker, string $clientId, string $topic, string $message) {
    $broker->publish($clientId, 'test', 'hello', 2);
    
    $broker->close($clientId);
});

$broker->start();
```
