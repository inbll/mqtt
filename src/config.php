<?php

return [
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
