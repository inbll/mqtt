<?php

namespace Inbll\Mqtt\Results;

/**
 * Class Result
 */
class Result
{
    /**
     * 报文类型
     *
     * @var int
     */
    protected $packetType;

    /**
     * 数据包长度
     *
     * @var int
     */
    protected $bodyLength;


    /**
     * @return int
     */
    public function getPacketType(): int
    {
        return $this->packetType;
    }

    /**
     * @param int $packetType
     */
    public function setPacketType(int $packetType): void
    {
        $this->packetType = $packetType;
    }

    /**
     * @return int
     */
    public function getBodyLength(): int
    {
        return $this->bodyLength;
    }

    /**
     * @param int $bodyLength
     */
    public function setBodyLength(int $bodyLength): void
    {
        $this->bodyLength = $bodyLength;
    }
}
