<?php

namespace Inbll\Mqtt\Protocols;

use Inbll\Mqtt\Exceptions\ConnectException;
use Inbll\Mqtt\Results\ConnectResult;
use Inbll\Mqtt\Results\PublishResult;
use Inbll\Mqtt\Results\ResponseConfirmResult;
use Inbll\Mqtt\Results\Result;
use Inbll\Mqtt\Results\SubscribeResult;
use Inbll\Mqtt\Results\UnSubscribeResult;

/**
 * Class ThreeProtocol
 * @package Inbll\Mqtt\Protocols
 */
class ThreeProtocol
{
    /**
     * 报文类型-客户端请求连接服务端
     */
    const PACKET_TYPE_CONNECT = 1;

    /**
     * 报文类型-连接报文确认
     */
    const PACKET_TYPE_CONNACK = 2;

    /**
     * 报文类型-发布消息
     */
    const PACKET_TYPE_PUBLISH = 3;

    /**
     * 报文类型-QoS1消息发布收到确认
     */
    const PACKET_TYPE_PUBACK = 4;

    /**
     * 报文类型-QoS2发布收到（保证交付第一步）
     */
    const PACKET_TYPE_PUBREC = 5;

    /**
     * 报文类型-QoS2发布释放（保证交付第二步）
     */
    const PACKET_TYPE_PUBREL = 6;

    /**
     * 报文类型-QoS2消息发布完成（保证交互第三步）
     */
    const PACKET_TYPE_PUBCOMP = 7;

    /**
     * 报文类型-客户端订阅请求
     */
    const PACKET_TYPE_SUBSCRIBE = 8;

    /**
     * 报文类型-订阅请求报文确认
     */
    const PACKET_TYPE_SUBACK = 9;

    /**
     * 报文类型-客户端取消订阅请求
     */
    const PACKET_TYPE_UNSUBSCRIBE = 10;

    /**
     * 报文类型-取消订阅报文确认
     */
    const PACKET_TYPE_UNSUBACK = 11;

    /**
     * 报文类型-心跳请求
     */
    const PACKET_TYPE_PINGREQ = 12;

    /**
     * 报文类型-心跳响应
     */
    const PACKET_TYPE_PINGRESP = 13;

    /**
     * 报文类型-客户端断开连接
     */
    const PACKET_TYPE_DISCONNECT = 14;


    /**
     * 连接返回码-接受
     */
    const CONNECT_RETURN_CODE_RECEIVE = 0;


    /**
     * 订阅-成功QOS0
     */
    const SUBACK_CODE_QOS0 = 0;

    /**
     * 订阅-成功QOS1
     */
    const SUBACK_CODE_QOS1 = 1;

    /**
     * 订阅-成功QOS2
     */
    const SUBACK_CODE_QOS2 = 2;

    /**
     * 订阅-失败
     */
    const SUBACK_CODE_FAIL = 128;


    /**
     * QOS等级-0
     */
    const QOS0 = 0;

    /**
     * QOS等级-1
     */
    const QOS1 = 1;

    /**
     * QOS等级-2
     */
    const QOS2 = 2;


    /**
     * 包装整体数据包
     *
     * @param int $packType
     * @param string|null $headData
     * @param string $body
     * @return string
     */
    public static function packBody(int $packType, ?string $headData, string $body = ''): string
    {
        // 组装固定报头
        $fixedHeader = ($packType << 4);
        if ($headData) {
            $fixedHeader |= $headData;
        }

        // 整体拼装格式：固定表头+剩余长度+可变报文和有效负荷
        return chr($fixedHeader) . static::buildBodyLength($body) . $body;
    }

    /**
     * 连接确认
     *
     * @param bool $sessionPresent
     * @param int $returnCode
     * @return string
     */
    public static function connack(bool $sessionPresent, int $returnCode = self::CONNECT_RETURN_CODE_RECEIVE)
    {
        $body = chr($sessionPresent ? 1 : 0) . chr($returnCode);
        return static::packBody(self::PACKET_TYPE_CONNACK, null, $body);
    }

    /**
     * 发送
     *
     * @param string $topicName
     * @param string $message
     * @param int|null $messageId
     * @param int $qos
     * @param bool $dup
     * @param bool $retain
     * @return string
     */
    public static function publish(string $topicName, string $message, ?int $messageId, int $qos, bool $dup = false, bool $retain = false)
    {
        $headData = 0x00;

        // 重发标志 第3位
        if ($dup) {
            $headData |= 0x08;
        }

        // 重发标志 第1-2位
        $headData |= $qos << 1;

        // 保留标志 第0位
        if ($retain) {
            $headData |= 0x01;
        }

        $body = static::buildString($topicName);

        // MQTT-2.3.1-2
        if ($qos > 0) {
            $body .= static::buildInt($messageId);
        }

        $body .= $message;

        return static::packBody(self::PACKET_TYPE_PUBLISH, $headData, $body);
    }

    /**
     * QOS1响应
     *
     * @param int $messageId
     * @return string
     */
    public static function puback(int $messageId)
    {
        return static::packBody(self::PACKET_TYPE_PUBACK, null, static::buildInt($messageId));
    }

    /**
     * QOS2-确认第一步
     *
     * @param int $messageId
     * @return string
     */
    public static function pubrec(int $messageId)
    {
        return static::packBody(self::PACKET_TYPE_PUBREC, null, static::buildInt($messageId));
    }

    /**
     * QOS2-确认第二步
     *
     * @param int $messageId
     * @return string
     */
    public static function pubrel(int $messageId)
    {
        $headData = 0x02;

        return static::packBody(self::PACKET_TYPE_PUBREL, $headData, static::buildInt($messageId));
    }

    /**
     * QOS2-确认第三步
     *
     * @param int $messageId
     * @return string
     */
    public static function pubcomp(int $messageId)
    {
        return static::packBody(self::PACKET_TYPE_PUBCOMP, null, static::buildInt($messageId));
    }

    /**
     * 订阅确认
     *
     * @param int $messageId
     * @param array $res
     * @return string
     */
    public static function suback(int $messageId, array $res)
    {
        $body = static::buildInt($messageId);
        foreach ($res as $qos) {
            $body .= chr($qos);
        }

        return static::packBody(self::PACKET_TYPE_SUBACK, null, $body);
    }

    /**
     * QOS2-确认第三步
     *
     * @param int $messageId
     * @return string
     */
    public static function unsuback(int $messageId)
    {
        return static::packBody(self::PACKET_TYPE_UNSUBACK, null, static::buildInt($messageId));
    }

    /**
     * 心跳响应
     *
     * @return string
     */
    public static function pingresp()
    {
        return static::packBody(self::PACKET_TYPE_PINGRESP, null, '');
    }

    /**
     * 解码协议
     *
     * @param string $body
     * @return Result
     * @throws ConnectException
     */
    public static function decode(string $body): Result
    {
        if (!isset($body[0])) {
            throw new ConnectException(ConnectException::ERROR);
        }

        $fixedHeader = ord($body[0]); // 固定表头
        $packetType = ($fixedHeader & 0xF0) >> 4; // 报文类型
        list($body, $bodyLength) = static::getBodyInfo($body); // 获取可变报文和长度

        $result = null;
        switch ($packetType) {
            case self::PACKET_TYPE_CONNECT:
                $result = new ConnectResult();

                // 获取协议名称
                list($protocolName, $offset) = static::readString($body, 0);
                $result->setProtocolName($protocolName);

                // 获取协议级别
                $result->setProtocolVersion(ord($body[$offset]));


                // 只支持 3.1、3.1.1 By MQTT-3.1.2-2
                if (!in_array($result->getProtocolVersion(), [3, 4])) {
                    throw new ConnectException(ConnectException::PROTOCOL_NOT_SUPPORT);
                }

                // 协议是否匹配 By MQTT-3.1.2-1
                $versionCheck = ($result->getProtocolVersion() == 4 && $protocolName == 'MQTT') || ($result->getProtocolVersion() == 3 && $protocolName == 'MQIsdp');
                if ($versionCheck == false) {
                    throw new ConnectException(ConnectException::PROTOCOL_NOT_SUPPORT);
                }

                $offset += 1;
                $connectFlags = ord($body[$offset]); // 获取连接标志

                // 保留标志位是否为0 By MQTT-3.1.2-3
                if (($connectFlags & 0x01) == 1) {
                    throw new ConnectException(ConnectException::ERROR);
                }

                // 连接标志
                $result->setCleanSession(($connectFlags & 0x02) == 0x02);
                $result->setWillFlag(($connectFlags & 0x04) == 0x04);
                $result->setWillQos(($connectFlags & 0x18) >> 3);
                $result->setWillRetain(($connectFlags & 0x20) == 0x20);
                $result->setUsernameFlag(($connectFlags & 0x80) == 0x80);
                $result->setPasswordFlag(($connectFlags & 0x40) == 0x40);

                // 遗嘱标志为1需要验证
                if ($result->isWillFlag()) {
                    // Qos等级 只能传0、1、2 By MQTT-3.1.2-14
                    if (!in_array($result->getWillQos(), [0, 1, 2])) {
                        throw new ConnectException(ConnectException::ERROR);
                    }
                }


                // 保持连接时长（秒）
                $result->setKeepAlive(static::stringLength(substr($body, $offset + 1, 2)));


                $payload = substr($body, $offset + 3); // 有效负荷

                // 获取客户端标识
                list($clientId, $payloadOffset) = static::readString($payload, 0);
                $result->setClientId($clientId);

                if ($result->isWillFlag()) {
                    // 获取遗嘱主题
                    list($willTopic, $payloadOffset) = static::readString($payload, $payloadOffset);
                    $result->setWillTopic($willTopic);

                    // 获取遗嘱消息
                    list($willMessage, $payloadOffset) = static::readString($payload, $payloadOffset);
                    $result->setWillMessage($willMessage);
                }

                if ($result->isUsernameFlag()) {
                    // 获取用户名
                    list($username, $payloadOffset) = static::readString($payload, $payloadOffset);
                    $result->setUsername($username);
                }

                if ($result->isPasswordFlag()) {
                    // 获取密码
                    list($password, $payloadOffset) = static::readString($payload, $payloadOffset);
                    $result->setPassword($password);
                }

                break;
            case self::PACKET_TYPE_PUBLISH:
                $result = new PublishResult();
                $result->setDup(($fixedHeader & 0x08) == 0x08); // DUP
                $result->setQos(($fixedHeader & 0x06) >> 1); // QOS
                $result->setRetain(($fixedHeader & 0x01) == 0x01); // 保留标志

                // 获取主题
                list($topicName, $offset) = static::readString($body, 0);
                $result->setTopicName($topicName);

                // 获取消息ID
                if ($result->getQos()) {
                    $result->setMessageId(static::stringLength(substr($body, $offset, 2))); // 消息ID
                    $offset += 2;
                }

                // 获取内容
                $result->setContent(isset($body[$offset]) ? substr($body, $offset) : '');

                break;
            case self::PACKET_TYPE_PUBCOMP:
            case self::PACKET_TYPE_PUBREL:
            case self::PACKET_TYPE_PUBREC:
            case self::PACKET_TYPE_PUBACK:
                $result = new ResponseConfirmResult();
                $result->setMessageId(static::stringLength($body)); // 消息ID
                break;
            case self::PACKET_TYPE_SUBSCRIBE:
                $result = new SubscribeResult();
                $result->setMessageId(static::stringLength(substr($body, 0, 2))); // 消息ID

                $payload = substr($body, 2); // 有效负荷

                $subscribes = [];
                $payloadOffset = 0;
                while (isset($payload[$payloadOffset])) {
                    list($topicFilter, $payloadOffset) = static::readString($payload, $payloadOffset);

                    $qos = ord($payload[$payloadOffset]) & 0x03;
                    $payloadOffset += 1;

                    $subscribes[] = [
                        'topic_filter' => $topicFilter,
                        'qos' => $qos
                    ];
                };

                $result->setSubscribes($subscribes);
                break;
            case self::PACKET_TYPE_UNSUBSCRIBE:
                $result = new UnSubscribeResult();
                $result->setMessageId(static::stringLength(substr($body, 0, 2))); // 消息ID

                $payload = substr($body, 2); // 有效负荷

                $subscribes = [];
                $payloadOffset = 0;
                while (isset($payload[$payloadOffset])) {
                    list($topicFilter, $payloadOffset) = static::readString($payload, $payloadOffset);

                    $subscribes[] = $topicFilter;
                };

                $result->setSubscribes($subscribes);
                break;
            default:
                $result = new Result();
        }

        if (!$result instanceof Result) {
            throw new ConnectException(ConnectException::ERROR);
        }

        $result->setPacketType($packetType);
        $result->setBodyLength($bodyLength);

        return $result;
    }

    /**
     * 检查过滤器是否合格
     *
     * @param string $topicFilter
     * @param int $qos
     * @return bool
     */
    public static function checkSubscribe(string $topicFilter, int $qos): bool
    {
        if ($topicFilter == '') {
            return false;
        }

        $topicFilter = explode('/', $topicFilter);
        $topicCount = count($topicFilter);
        foreach ($topicFilter as $k => $topic) {
            $isEnd = $topicCount == $k + 1;

            // 1.如果有+或者#字符，但是还包含其它字符的话，就代表不通过
            if (((strpos($topic, '+') === true) || (strpos($topic, '#') === true)) && strlen($topic) > 1) {
                return false;
            }

            // 2.MQTT-4.7.1-2
            if ($topic == '#' && !$isEnd) {
                return false;
            }
        }

        if (static::checkQos($qos) == false) {
            return false;
        }

        return true;
    }

    /**
     * @param int $qos
     * @return bool
     */
    public static function checkQos(int $qos): bool
    {
        return in_array($qos, [static::QOS0, static::QOS1, static::QOS2]);
    }

    /**
     * 匹配主题是否匹配过滤器
     *
     * @param string $publishTopic
     * @param string $topicFilter
     * @return bool
     */
    public static function matchTopicSubscribe(string $publishTopic, string $topicFilter): bool
    {
        $exPublishTopic = explode('/', $publishTopic);
        $topicCount = count($exPublishTopic);

        $exTopicFilter = explode('/', $topicFilter);
        $filterCount = count($exTopicFilter);

        // 如果主题层级比过滤器高，且过滤器最后一层不是#的话，就代表不匹配
        if (($topicCount > $filterCount) && (end($exTopicFilter) != '#')) {
            return false;
        } else {
            foreach ($exTopicFilter as $k => $levelTopicFilter) {
                $isEnd = $filterCount == ($k + 1);

                // 如果有同层的话
                if (isset($exPublishTopic[$k])) {
                    // 过滤器当前层级是否不等于+，且字符对比不匹配
                    if (($levelTopicFilter != '+') && ($levelTopicFilter != $exPublishTopic[$k])) {
                        // 过滤器当前层级不是最后一层，或者不等于#
                        if (($isEnd == false) || ($isEnd && ($levelTopicFilter != '#'))) {
                            return false;
                        }
                    }
                } else {
                    // 没有同层，代表过滤器层级比主题层级高

                    // 如果不是最后一层，或者是最后一层但不等于#，就代表不匹配
                    if ($isEnd == false || ($levelTopicFilter != '#')) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * 生成字节长度
     *
     * @param $body
     * @return string
     */
    public static function buildBodyLength($body)
    {
        $bodyLength = strlen($body);
        $lengthByte = '';

        do {
            $encodedByte = $bodyLength % 128;
            $bodyLength = $bodyLength >> 7;

            if ($bodyLength > 0) {
                $encodedByte = ($encodedByte | 0x80);
            }

            $lengthByte .= chr($encodedByte);
        } while ($bodyLength > 0);

        return $lengthByte;
    }

    /**
     * 返回数据包和长度
     *
     * @param $body
     * @return array
     */
    public static function getBodyInfo($body): array
    {
        $offset = $multiplier = 1;
        $length = 0;

        do {
            if (!isset($body[$offset])) {
                break;
            }

            $digit = ord($body[$offset]);
            $length += ($digit & 127) * $multiplier;
            $multiplier *= 128;
            $offset++;
        } while ($offset <= 4 && (($digit & 128) != 0));

        $body = substr($body, $offset);

        return [$body, $length];
    }

    /**
     * 转字节
     *
     * @param int $int
     * @return string
     */
    public static function buildInt(int $int): string
    {
        return pack('n', $int);
    }

    /**
     * 字节转长度
     *
     * @param string $bytes
     * @return int
     */
    public static function stringLength(string $bytes): int
    {
        $unpack = unpack('n', $bytes);

        return end($unpack);
    }

    /**
     * 读取字符串
     *
     * @param string $body
     * @param int $offset
     * @return array
     */
    public static function readString(string $body, int $offset): array
    {
        $length = static::stringLength(substr($body, $offset, 2));

        $offset += 2;
        $value = substr($body, $offset, $length);
        $offset += $length;

        return [$value, $offset];
    }

    /**
     * 字符串增加长度字节
     *
     * @param string $string
     * @return string
     */
    public static function buildString(string $string): string
    {
        $length = strlen($string);
        return pack('n', $length) . $string;
    }
}
