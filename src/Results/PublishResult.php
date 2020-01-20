<?php

namespace Inbll\Mqtt\Results;

/**
 * 发送消息实体类
 *
 * Class ConnectResult
 */
class PublishResult extends Result
{
    /**
     * @var string
     */
    protected $clientId;

    /**
     * 是否重发
     *
     * @var bool
     */
    protected $dup;

    /**
     * QOS等级
     *
     * @var int
     */
    protected $qos;

    /**
     * 是否保留
     *
     * @var bool
     */
    protected $retain;

    /**
     * 主题
     *
     * @var string
     */
    protected $topicName;

    /**
     * 消息ID
     *
     * @var int
     */
    protected $messageId;

    /**
     * 内容
     *
     * @var string
     */
    protected $content;

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
     * @return bool
     */
    public function isDup(): bool
    {
        return $this->dup;
    }

    /**
     * @param bool $dup
     */
    public function setDup(bool $dup): void
    {
        $this->dup = $dup;
    }

    /**
     * @return int
     */
    public function getQos(): int
    {
        return $this->qos;
    }

    /**
     * @param int $qos
     */
    public function setQos(int $qos): void
    {
        $this->qos = $qos;
    }

    /**
     * @return bool
     */
    public function isRetain(): bool
    {
        return $this->retain;
    }

    /**
     * @param bool $retain
     */
    public function setRetain(bool $retain): void
    {
        $this->retain = $retain;
    }

    /**
     * @return string
     */
    public function getTopicName(): string
    {
        return $this->topicName;
    }

    /**
     * @param string $topicName
     */
    public function setTopicName(string $topicName): void
    {
        $this->topicName = $topicName;
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
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @param string $content
     */
    public function setContent(string $content): void
    {
        $this->content = $content;
    }
}
