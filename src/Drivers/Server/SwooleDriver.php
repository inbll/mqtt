<?php

namespace Inbll\Mqtt\Drivers\Server;

use Inbll\Mqtt\Exceptions\ConnectException;
use Inbll\Mqtt\Protocols\ThreeProtocol;
use Inbll\Mqtt\Results\ConnectResult;
use Inbll\Mqtt\Results\PublishResult;
use Inbll\Mqtt\Results\ResponseConfirmResult;
use Inbll\Mqtt\Results\Result;
use Inbll\Mqtt\Support\Arr;
use Swoole\Server;
use Swoole\Table;

/**
 * Swoole driver
 *
 * Class ServerDriver
 */
class SwooleDriver extends ServerDriver
{
    const DRIVER_NAME = 'swoole';


    /**
     * @var Server
     */
    protected $server;

    /**
     * @var Table
     */
    protected $fdMemoryTable;


    /**
     * 启动
     */
    public function start(): void
    {
        // 初始化Server
        $this->server = new Server('0.0.0.0', $this->manager->getConfig('port'), SWOOLE_BASE);
        $this->server->set(array_merge($this->config, [
            'open_mqtt_protocol' => 1
        ]));

        $this->bindWorkerStart();
        $this->bindReceive();
        $this->bindTask();
        $this->initCache();

        $this->server->start();
    }

    /**
     * 进件启动事件
     */
    protected function bindWorkerStart(): void
    {
        $this->server->on('WorkerStart', function (Server $server, $workerId) {
            if ($workerId == 0) {
                // keepAlive的心跳轮询
                $this->keepAlivePoll();
            }
        });
    }

    /**
     * 接收数据事件
     */
    protected function bindReceive(): void
    {
        $this->server->on('Receive', function (Server $server, int $fd, int $reactorId, string $data) {
            try {
                /** @var Result $result */
                $result = ThreeProtocol::decode($data);
            } catch (ConnectException $exception) {
                $this->connack($fd, $exception->getCode());
                $server->close($fd);
            }

            // 获取客户端ID
            $clientId = null;
            if ($result->getPacketType() != ThreeProtocol::PACKET_TYPE_CONNECT) {
                $clientId = $this->getClientId($fd);
                if (!$clientId) {
                    $server->close($fd);
                }
            }

            switch ($result->getPacketType()) {
                case ThreeProtocol::PACKET_TYPE_CONNECT:
                    /** @var ConnectResult $result */

                    $returnCode = ThreeProtocol::CONNECT_RETURN_CODE_RECEIVE;

                    // 授权回调事件
                    $authorizeResult = $this->manager->emit('authorize', $this, $result->getClientId(), $result->getUsername(), $result->getPassword());
                    if ($authorizeResult === false) {
                        $returnCode = ConnectException::RETURN_CODE_USER_INVALID;
                    }

                    // 单点登录
                    $this->clientSingle($fd, $result);

                    $this->connack($fd, $returnCode);

                    if ($returnCode !== ThreeProtocol::CONNECT_RETURN_CODE_RECEIVE) {
                        $server->close($fd);
                        return;
                    }

                    // 连接后事件
                    $this->manager->emit('connected', $this, $result->getClientId());
                    break;
                case ThreeProtocol::PACKET_TYPE_PUBLISH:
                    /** @var PublishResult $result */
                    $result->setClientId($clientId);

                    if ($result->getQos() == ThreeProtocol::QOS1) {
                        $this->puback($fd, $result->getMessageId());
                    } else if ($result->getQos() == ThreeProtocol::QOS2) {
                        $this->pubrec($fd, $result->getMessageId());
                    }

                    // 接收消息后事件
                    $this->manager->emit('message', $this, $clientId, $result->getTopicName(), $result->getContent());
                    break;
                case ThreeProtocol::PACKET_TYPE_PUBACK:
                    /** @var ResponseConfirmResult $result */

                    // MQTT-2.3.1-6
                    if ($this->manager->hasMessageId($clientId, $result->getMessageId() ?: '') == false) {
                        $server->close($fd);
                        return;
                    }
                    break;
                case ThreeProtocol::PACKET_TYPE_PUBREC:
                    // MQTT-2.3.1-6
                    if ($this->manager->hasMessageId($clientId, $result->getMessageId() ?: '') == false) {
                        $server->close($fd);
                        return;
                    }

                    $this->pubrel($fd, $result->getMessageId());

                    break;
                case ThreeProtocol::PACKET_TYPE_PUBREL:
                    $this->pubcomp($fd, $result->getMessageId());

                    break;
                case ThreeProtocol::PACKET_TYPE_PUBCOMP:
                    // MQTT-2.3.1-6
                    if ($this->manager->hasMessageId($clientId, $result->getMessageId() ?: '') == false) {
                        $server->close($fd);
                    }

                    break;
                case ThreeProtocol::PACKET_TYPE_PINGREQ:
                    $this->pingresp($fd);
                    break;
                case ThreeProtocol::PACKET_TYPE_DISCONNECT:
                    $server->close($fd);
                    break;
            }
        });

        $this->server->on('Close', function (Server $server, int $fd, int $reactorId) {
            // 释放缓存数据
            $clientId = $this->getClientId($fd);
            if ($clientId) {
                $this->manager->delClient($clientId);

                // 触发关闭事件
                $this->manager->emit('close', $this, $clientId);
            }

            $this->fdMemoryTable->del($fd);
        });
    }

    protected function bindTask()
    {
        $this->server->on('Task', function (Server $server, Server\Task $task) {
            $type = Arr::get($task->data, 'type');

            switch ($type) {
                case 'publish':
                    /** @var PublishResult $publishResult */
                    $publishResult = Arr::get($task->data, 'result');

                    $this->publish($publishResult->getClientId(), $publishResult->getTopicName(), $publishResult->getContent(), $publishResult->getQos());
                    break;
            }

            $task->finish(true);
        });

        $this->server->on('Finish', function (Server $server, $taskId, $data) {
            // TODO
        });
    }

    /**
     * 重置缓存
     */
    protected function initCache(): void
    {
        $this->fdMemoryTable = new Table($this->getConfig('table_size'));
        $this->fdMemoryTable->column('client_id', Table::TYPE_STRING, 32);
        $this->fdMemoryTable->create();
    }

    /**
     * 推送
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
    public function publish(string $clientId, string $topicName, string $message, int $qos, bool $dup = false, bool $retain = false): bool
    {
        $result = false;

        $clientInfo = $this->manager->getClient($clientId);
        if ($clientInfo) {
            $messageId = null;
            if ($qos) {
                $messageId = $this->manager->buildMessageId($clientId);

                // 30秒后自动销毁
                $this->server->after(30000, function () use ($clientId, $messageId) {
                    $this->manager->deleteMessageId($clientId, $messageId);
                });
            }

            $result = $this->server->send($clientInfo['fd'], ThreeProtocol::publish($topicName, $message, $messageId, $qos, $dup, $retain));
        }

        $this->manager->emit('published', $this, $result, $clientId, $topicName, $message);

        return $result;
    }

    /**
     * 关闭连接
     *
     * @param string $clientId
     */
    public function close(string $clientId): void
    {
        $clientInfo = $this->manager->getClient($clientId);
        if ($clientInfo) {
            $this->server->close($clientInfo['fd']);
        }
    }

    /**
     * 连接确认
     *
     * @param int $fd
     * @param int $returnCode
     * @param bool $sessionPresent
     * @return bool
     */
    protected function connack(int $fd, int $returnCode = 0, bool $sessionPresent = true): bool
    {
        return $this->server->send($fd, ThreeProtocol::connack($sessionPresent, $returnCode));
    }

    /**
     * QOS1响应
     *
     * @param int $fd
     * @param int $messageId
     * @return bool
     * @throws \Exception
     */
    protected function puback(int $fd, int $messageId): bool
    {
        return $this->server->send($fd, ThreeProtocol::puback($messageId));
    }

    /**
     * QOS2-确认第一步
     *
     * @param int $fd
     * @param int $messageId
     * @return bool
     * @throws \Exception
     */
    protected function pubrec(int $fd, int $messageId): bool
    {
        return $this->server->send($fd, ThreeProtocol::pubrec($messageId));
    }

    /**
     * QOS2-确认第二步
     *
     * @param int $fd
     * @param int $messageId
     * @return bool
     * @throws \Exception
     */
    protected function pubrel(int $fd, int $messageId): bool
    {
        return $this->server->send($fd, ThreeProtocol::pubrel($messageId));
    }

    /**
     * QOS2-确认第三步
     *
     * @param int $fd
     * @param int $messageId
     * @return bool
     * @throws \Exception
     */
    protected function pubcomp(int $fd, int $messageId): bool
    {
        return $this->server->send($fd, ThreeProtocol::pubcomp($messageId));
    }

    /**
     * 心跳响应
     *
     * @param int $fd
     * @return bool
     * @throws \Exception
     */
    protected function pingresp(int $fd): bool
    {
        return $this->server->send($fd, ThreeProtocol::pingresp());
    }

    /**
     * 单点登录
     *
     * @param int $fd
     * @param ConnectResult $connectResult
     */
    protected function clientSingle(int $fd, ConnectResult $connectResult): void
    {
        $clientInfo = $this->manager->getClient($connectResult->getClientId());
        $cacheFd = Arr::get($clientInfo, 'fd');

        if ($cacheFd && $cacheFd != $fd) {
            $this->server->close($cacheFd);
        }

        $this->fdMemoryTable->set($fd, [
            'client_id' => $connectResult->getClientId(),
        ]);

        $this->manager->addClient($connectResult->getClientId(), [
            'fd' => $fd,
            'client_id' => $connectResult->getClientId(),
            'protocol_version' => $connectResult->getProtocolVersion(),
            'keep_alive' => $connectResult->getKeepAlive(),
            'clean_session' => (int)$connectResult->isCleanSession(),
            'will_flag' => (int)$connectResult->isWillFlag(),
            'will_qos' => $connectResult->getWillQos(),
            'will_retain' => (int)$connectResult->isWillRetain(),
            'username_flag' => (int)$connectResult->isUsernameFlag(),
            'password_flag' => (int)$connectResult->isPasswordFlag(),
            'will_topic' => (string)$connectResult->getWillTopic(),
            'will_message' => (string)$connectResult->getWillMessage(),
            'username' => (string)$connectResult->getUsername(),
        ]);
    }

    /**
     * 返回客户端ID
     *
     * @param int $fd
     * @return null|string
     */
    protected function getClientId(int $fd): ?string
    {
        return $this->fdMemoryTable->get($fd, 'client_id') ?: null;
    }

    /**
     * MQTT的心跳轮询
     */
    protected function keepAlivePoll(): void
    {
        // keep_alive心跳 MQTT-3.1.2-24
        $this->server->tick(5000, function () {
            foreach ($this->server->connections as $fd) {
                $clientId = $this->getClientId($fd);
                if (!$clientId) {
                    $this->server->close($fd);

                    continue;
                }

                $clientInfo = $this->manager->getClient($clientId);
                $fdClientInfo = $this->server->getClientInfo($fd);
                $keepAlive = (int)Arr::get($clientInfo, 'keep_alive');
                $keepAlive *= 1.5;

                if ($keepAlive && $fdClientInfo['last_time'] + $keepAlive < time()) {
                    $this->server->close($fd);
                }
            }
        });
    }
}
