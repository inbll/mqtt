<?php

namespace Inbll\Mqtt\Contracts;

interface ServerInterface
{
    /**
     * start
     */
    public function start(): void;

    /**
     * publish
     *
     * @param string $clientId
     * @param string $topicName
     * @param string $message
     * @param int $qos
     * @param bool $dup
     * @param bool $retain
     * @return bool
     * @throws \Exception
     */
    public function publish(string $clientId, string $topicName, string $message, int $qos, bool $dup = false, bool $retain = false): bool;

    /**
     * close client
     *
     * @param string $clientId
     */
    public function close(string $clientId): void;
}