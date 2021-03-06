<?php

namespace Inbll\Mqtt\Results;

/**
 * Class ResponseConfirmResult
 * @package Inbll\Mqtt\Results
 */
class ResponseConfirmResult extends Result
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
}
