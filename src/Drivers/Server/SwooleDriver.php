<?php

namespace Inbll\Mqtt\Drivers\Server;

use Inbll\Mqtt\Exceptions\ConnectException;
use Inbll\Mqtt\Protocols\ThreeProtocol;
use Inbll\Mqtt\Results\ConnectResult;
use Inbll\Mqtt\Results\PublishResult;
use Inbll\Mqtt\Results\ResponseConfirmResult;
use Inbll\Mqtt\Results\Result;
use Carbon\Carbon;
use Inbll\Mqtt\Support\Arr;
use Illuminate\Support\Facades\Redis;
use Swoole\Server;
use Swoole\Table;

/**
 * Swoole driver
 *
 * Class ServerDriver
 */
class SwooleDriver extends ServerInterfaceDriver
{
    /**
     * 消息集合KEY
     */
    const MESSAGE_SET_KEY = 'mqtt_%s_message_ids';


    /**
     * 协议版本
     */
    protected $version;

    /**
     * 端口号
     *
     * @var int
     */
    protected $port;

    /**
     * 驱动配置
     *
     * @var array
     */
    protected $config;

    /**
     * @var Server
     */
    protected $server;

    /**
     * @var Table
     */
    protected $fdMemoryTable;

    /**
     * @var Table
     */
    protected $clientMemoryTable;


    /**
     *
     * SwooleDriver constructor.
     * @param int $port
     * @param array $config
     */
    public function __construct(int $port, array $config = [])
    {
        $this->port = $port;
        $this->config = $config;
    }

    /**
     * @param string $name
     * @return string|null
     */
    public function getConfig(string $name): ?string
    {
        return Arr::get($this->config, $name);
    }

    /**
     * 启动
     */
    public function start(): void
    {
        // 初始化Server
        $this->server = new Server('0.0.0.0', $this->port, SWOOLE_BASE);
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
                    $authorizeResult = $this->emit('authorize', $this, $result->getClientId(), $result->getUsername(), $result->getPassword());
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
                    $this->emit('connected', $this, $result->getClientId());
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
                    $this->emit('message', $this, $clientId, $result->getTopicName(), $result->getContent());
                    break;
                case ThreeProtocol::PACKET_TYPE_PUBACK:
                    /** @var ResponseConfirmResult $result */

                    // MQTT-2.3.1-6
                    if ($this->hasMessageId($fd, $result->getMessageId() ?: '') == false) {
                        $server->close($fd);
                        return;
                    }
                    break;
                case ThreeProtocol::PACKET_TYPE_PUBREC:
                    // MQTT-2.3.1-6
                    if ($this->hasMessageId($fd, $result->getMessageId() ?: '') == false) {
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
                    if ($this->hasMessageId($fd, $result->getMessageId() ?: '') == false) {
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
                // 触发关闭事件
                $this->emit('close', $this, $clientId);

                $this->clientMemoryTable->del($clientId);
            }

            Redis::del(sprintf(self::MESSAGE_SET_KEY, $fd));
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
        $messageKeys = Redis::keys(str_replace('%s', '*', self::MESSAGE_SET_KEY));
        if ($messageKeys) {
            Redis::del($messageKeys);
        }

        $this->fdMemoryTable = new Table($this->getConfig('table_size'));
        $this->fdMemoryTable->column('client_id', Table::TYPE_STRING, 32);
        $this->fdMemoryTable->create();


        // 初始化客户端表
        $this->clientMemoryTable = new Table($this->getConfig('table_size'));
        $this->clientMemoryTable->column('client_id', Table::TYPE_STRING, 32);
        $this->clientMemoryTable->column('fd', Table::TYPE_INT);
        $this->clientMemoryTable->column('protocol_version', Table::TYPE_STRING, 1);
        $this->clientMemoryTable->column('keep_alive', Table::TYPE_INT);
        $this->clientMemoryTable->column('clean_session', Table::TYPE_STRING, 1);
        $this->clientMemoryTable->column('will_flag', Table::TYPE_STRING, 1);
        $this->clientMemoryTable->column('will_qos', Table::TYPE_STRING, 1);
        $this->clientMemoryTable->column('will_retain', Table::TYPE_STRING, 1);
        $this->clientMemoryTable->column('username_flag', Table::TYPE_STRING, 1);
        $this->clientMemoryTable->column('password_flag', Table::TYPE_STRING, 1);
        $this->clientMemoryTable->column('will_topic', Table::TYPE_STRING, 256);
        $this->clientMemoryTable->column('will_message', Table::TYPE_STRING, 512);
        $this->clientMemoryTable->column('username', Table::TYPE_STRING, 128);
        $this->clientMemoryTable->column('password', Table::TYPE_STRING, 512);
        $this->clientMemoryTable->create();
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

        $fd = $this->getClientInfo($clientId, 'fd');
        if ($fd) {
            $messageId = $qos ? $this->buildMessageId($fd) : null;

            $result = $this->server->send($fd, ThreeProtocol::publish($topicName, $message, $messageId, $qos, $dup, $retain));
        }

        $this->emit('published', $this, $result, $clientId, $topicName, $message);

        return $result;
    }

    /**
     * 关闭连接
     *
     * @param string $clientId
     */
    public function close(string $clientId): void
    {
        $fd = $this->getClientInfo($clientId, 'fd');
        if ($fd) {
            $this->server->close($fd);
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
     * 生成消息ID
     *
     * @param int $fd
     * @param int $recursion
     * @return int
     * @throws \Exception
     */
    protected function buildMessageId(int $fd, int $recursion = 10): int
    {
        $messageId = mt_rand(1, 65535);

        // 检测是否已有该消息ID,有的话则递归获取
        if ($this->hasMessageId($fd, $messageId)) {
            if ($recursion > 0) {
                return $this->buildMessageId($fd, $recursion - 1);
            } else {
                throw new \Exception('生成message_id失败');
            }
        }

        $key = sprintf(self::MESSAGE_SET_KEY, $fd);
        Redis::sAdd($key, $messageId);
        Redis::expireAt($key, Carbon::now()->addSeconds(60)->timestamp); // 续时间


        // 30秒后自动销毁
        $this->server->after(30000, function () use ($key, $messageId) {
            Redis::sRem($key, $messageId);
        });

        return $messageId;
    }

    /**
     * 检测消息ID是否存在
     *
     * @param int $fd
     * @param int $messageId
     * @return bool
     */
    protected function hasMessageId(int $fd, int $messageId): bool
    {
        return Redis::sIsMember(sprintf(self::MESSAGE_SET_KEY, $fd), $messageId);
    }

    /**
     * 单点登录
     *
     * @param int $fd
     * @param ConnectResult $connectResult
     */
    protected function clientSingle(int $fd, ConnectResult $connectResult): void
    {
        $clientInfo = $this->clientMemoryTable->get($connectResult->getClientId());
        $cacheFd = Arr::get($clientInfo, 'fd');

        if ($cacheFd && $cacheFd != $fd) {
            $this->server->close($cacheFd);
        }

        $this->clientMemoryTable->set($connectResult->getClientId(), [
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
            'password' => (string)$connectResult->getPassword(),
        ]);


        $this->fdMemoryTable->set($fd, [
            'client_id' => $connectResult->getClientId(),
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
     * 返回连接标识
     *
     * @param string $clientId
     * @param string $field
     * @return null|string
     */
    protected function getClientInfo(string $clientId, string $field): ?string
    {
        return $this->clientMemoryTable->get($clientId, $field) ?: null;
    }

    /**
     * MQTT的心跳轮询
     */
    protected function keepAlivePoll(): void
    {
        // keep_alive心跳 MQTT-3.1.2-24
        $this->server->tick(30000, function () {
            foreach ($this->server->connections as $fd) {
                $clientId = $this->getClientId($fd);
                if (!$clientId) {
                    $this->server->close($fd);

                    continue;
                }

                $fdClientInfo = $this->server->getClientInfo($fd);
                $keepAlive = (int)$this->getClientInfo($clientId, 'keep_alive');
                $keepAlive *= 1.5; //

                if ($keepAlive && $fdClientInfo['last_time'] + $keepAlive < time()) {
                    $this->server->close($fd);
                }
            }
        });
    }
}
