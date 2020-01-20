<?php

namespace Inbll\Mqtt\Results;

/**
 * 接收消息确认实体类
 *
 * Class ConnectResult
 */
class ResponseConfirmResult extends Result
{
    /**
     * 消息
     *
     * @var int
     */
    protected $messageId;


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
