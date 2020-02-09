<?php

namespace Inbll\Mqtt\Results;

/**
 * Class UnSubscribeResult
 * @package Inbll\Mqtt\Results
 */
class UnSubscribeResult extends Result
{
    /**
     * @var string
     */
    protected $clientId;

    /**
     * @var int
     */
    protected $messageId;

    /**
     * @var array
     */
    protected $subscribes;


    /**
     * @return string
     */
    public function getClientId(): string
    {
        return $this->clientId;
    }

    /**
     * @param string $clientId
     */
    public function setClientId(string $clientId): void
    {
        $this->clientId = $clientId;
    }

    /**
     * @return int
     */
    public function getMessageId(): int
    {
        return $this->messageId;
    }

    /**
     * @param int $messageId
     */
    public function setMessageId(int $messageId): void
    {
        $this->messageId = $messageId;
    }

    /**
     * @return array
     */
    public function getSubscribes(): array
    {
        return $this->subscribes;
    }

    /**
     * @param array $subscribes
     */
    public function setSubscribes(array $subscribes): void
    {
        $this->subscribes = $subscribes;
    }
}
