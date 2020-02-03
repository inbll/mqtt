<?php

return [
    /**
     * 默认驱动
     */
    'driver' => 'swoole',

    /**
     * 默认存储
     */
    'database' => 'redis',

    /**
     * 协议版本，现只支持 3.1、3.1.1
     */
    'version' => '3.1.1',

    /**
     * 端口号
     */
    'port' => 1883,

    /**
     * 驱动配置
     */
    'drivers' => [
        'swoole' => [
            // 内存表最大行数
            'table_size' => 32768,

            // 主进程数
            'worker_num' => 2,

            // 任务进程数
            'task_worker_num' => 2,

            // 任务进程协程化
            'task_enable_coroutine' => true,

            // socket的心跳检测
            'heartbeat_check_interval' => 10,
            'heartbeat_idle_time' => 120,
        ]
    ],

    /**
     * 数据库配置
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
