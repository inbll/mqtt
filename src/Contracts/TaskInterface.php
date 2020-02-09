<?php

namespace Inbll\Mqtt\Contracts;

use Swoole\Server;

/**
 * Interface TaskInterface
 * @package Inbll\Mqtt\Contracts
 */
interface TaskInterface
{
    /**
     * handle
     *
     * @param Server $swoole
     * @return mixed
     */
    public function handle(Server $swoole);

    /**
     * task handled result
     *
     * @return mixed
     */
    public function getResult();
}