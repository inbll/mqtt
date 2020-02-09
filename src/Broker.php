<?php

namespace Inbll\Mqtt;

use Inbll\Mqtt\Contracts\TaskInterface;
use Inbll\Mqtt\Database\DatabaseManager;
use Inbll\Mqtt\Database\Models\Client;
use Inbll\Mqtt\Database\Models\MessageId;
use Inbll\Mqtt\Database\Models\Socket;
use Inbll\Mqtt\Database\Models\TopicFilter;
use Inbll\Mqtt\Exceptions\ConnectException;
use Inbll\Mqtt\Protocols\ThreeProtocol;
use Inbll\Mqtt\Results\ConnectResult;
use Inbll\Mqtt\Results\PublishResult;
use Inbll\Mqtt\Results\ResponseConfirmResult;
use Inbll\Mqtt\Results\Result;
use Inbll\Mqtt\Results\SubscribeResult;
use Inbll\Mqtt\Results\UnSubscribeResult;
use Inbll\Mqtt\Support\Arr;
use Inbll\Mqtt\Tasks\MatchTopicSubscribe;
use Inbll\Mqtt\Tasks\TopicSubscribeDispatch;
use Swoole\Server;

/**
 * Mqtt Broker
 *
 * Class Broker
 * @package Inbll\Mqtt
 */
class Broker
{
    /**
     * @var array
     */
    protected $config = [];

    /**
     * events
     *
     * @var array
     */
    protected $eventClosures = [];

    /**
     * @var Server
     */
    protected $swoole;


    /**
     * Broker constructor.
     * @param array $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * @param string $name
     * @param null $default
     * @return mixed
     */
    public function getConfig(string $name, $default = null)
    {
        return Arr::get($this->config, $name, $default);
    }

    /**
     * bind event
     *
     * @param string $event
     * @param callable $callback
     */
    public function on(string $event, callable $callback): void
    {
        $this->eventClosures[$event] = $callback;
    }

    /**
     * emit event
     *
     * @param string $event
     * @param mixed ...$arguments
     * @return mixed
     */
    protected function emit(string $event, ...$arguments)
    {
        $callback = Arr::get($this->eventClosures, $event);
        if ($callback) {
            return call_user_func($callback, ...$arguments);
        }
    }

    protected function initSwoole(): void
    {
        $this->swoole = new Server('0.0.0.0', $this->getConfig('port'));
        $this->swoole->set(array_merge((array)$this->getConfig('swoole', []), [
            'open_mqtt_protocol' => true
        ]));

        $avgCallbacks = [function () {
            $this->swoole->tick(5000, function () {
                $this->keepAlive();
            });
        }];
        if (array_key_exists('start', $this->eventClosures)) {
            $avgCallbacks[] = function () {
                call_user_func($this->eventClosures['start'], $this);
            };
        }

        $workerNum = Arr::get($this->swoole->setting, 'worker_num', 1);
        $this->swoole->on('WorkerStart', function (Server $server, $workerId) use ($workerNum, $avgCallbacks) {
            $this->initDatabase();

            if ($server->taskworker) {
                return;
            }

            // Worker assignment start event
            $i = $workerId;
            while (isset($avgCallbacks[$i])) {
                call_user_func($avgCallbacks[$i]);

                $i += $workerNum;
            }
        });

        // Receive event
        $this->swoole->on('Receive', function (Server $server, int $fd, int $reactorId, string $data) {
            $this->receive($fd, $data);
        });

        // Closed event
        $this->swoole->on('Close', function (Server $server, int $fd, int $reactorId) {
            $this->closed($fd);
        });

        // Task event
        $this->swoole->on('Task', function (Server $server, Server\Task $task) {
            $taskClass = $task->data;
            if (!$taskClass instanceof TaskInterface) {
                return;
            }

            $taskClass->handle($server);
            $task->finish($taskClass);
        });

        // Task finish event
        $this->swoole->on('Finish', function (Server $server, $taskId, $taskClass) {
            if (!$taskClass instanceof TaskInterface) {
                return;
            }
            
            if ($taskClass instanceof MatchTopicSubscribe) {
                foreach ($taskClass->getResult() as $topicFilter) {
                    $this->swoole->task(new TopicSubscribeDispatch($taskClass->publishResult, $topicFilter));
                }
            }
        });
    }

    protected function initDatabase(): void
    {
        DatabaseManager::init((string)$this->getConfig('database'), $this->getConfig('databases'));
    }

    protected function reset()
    {
        $clientIds = Client::instance()->getIds();
        foreach ($clientIds as $clientId) {
            $clientInfo = Client::instance()->find($clientId);
            if ($clientInfo && (Arr::get($clientInfo, 'clean_session', 1) == 1)) {
                Client::instance()->delete($clientId);
            }
        }
    }

    public function start()
    {
        $this->initDatabase();
        $this->reset();

        $this->initSwoole();
        
        $this->swoole->start();
    }

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
    public function publish(string $clientId, string $topicName, string $message, int $qos, bool $dup = false, bool $retain = false): bool
    {
        $result = false;

        $clientInfo = Client::instance()->find($clientId);
        if ($clientInfo) {
            $messageId = null;
            if ($qos) {
                $messageId = MessageId::instance()->create($clientId);

                // MessageId destruct after 30 seconds
                $this->swoole->after(30000, function () use ($clientId, $messageId) {
                    MessageId::instance()->delete($clientId, $messageId);
                });
            }

            $result = $this->swoole->send($clientInfo['connection_id'], ThreeProtocol::publish($topicName, $message, $messageId, $qos, $dup, $retain));
        }

        if ($result) {
            $this->emit('published', $this, $result, $clientId, $topicName, $message);
        } else {
            $this->log('publish error');
        }

        return $result;
    }

    /**
     * Client Close
     *
     * @param string $clientId
     */
    public function close(string $clientId): void
    {
        $clientInfo = Client::instance()->find($clientId);
        $connectionId = Arr::get($clientInfo, 'connection_id');

        if ($connectionId) {
            $this->swoole->close($connectionId);
        }
    }

    /**
     * message out
     *
     * @param $message
     */
    public function log($message): void
    {
        echo $message . "\n";
    }

    /**
     * receive handle
     *
     * @param string $connectionId
     * @param string $data
     */
    protected function receive(string $connectionId, string $data): void
    {
        try {
            /** @var Result $result */
            $result = ThreeProtocol::decode($data);
        } catch (ConnectException $exception) {
            $this->log('illegal connection');

            $this->swoole->send($connectionId, ThreeProtocol::connack(false, $exception->getCode()));
            $this->swoole->close($connectionId);
            return;
        }

        $clientId = null;
        if ($result->getPacketType() != ThreeProtocol::PACKET_TYPE_CONNECT) {
            $clientId = Socket::instance()->getClientId($connectionId);
            if (!$clientId) {
                $this->log('clientId not found');
                $this->swoole->close($connectionId);
                return;
            }

            if (method_exists($result, 'setClientId')) {
                $result->setClientId($clientId);
            }
        }

        switch ($result->getPacketType()) {
            case ThreeProtocol::PACKET_TYPE_CONNECT:
                /** @var ConnectResult $result */
                $this->packetTypeByConnect($connectionId, $result);
                break;
            case ThreeProtocol::PACKET_TYPE_PUBLISH:
                /** @var PublishResult $result */
                $this->packetTypeByPublish($connectionId, $result);
                break;
            case ThreeProtocol::PACKET_TYPE_PUBACK:
                /** @var ResponseConfirmResult $result */
                $this->packetTypeByPuback($connectionId, $result);
                break;
            case ThreeProtocol::PACKET_TYPE_PUBREC:
                /** @var ResponseConfirmResult $result */
                $this->packetTypeByPubrec($connectionId, $result);
                break;
            case ThreeProtocol::PACKET_TYPE_PUBREL:
                /** @var ResponseConfirmResult $result */
                $this->packetTypeByPubrel($connectionId, $result);
                break;
            case ThreeProtocol::PACKET_TYPE_PUBCOMP:
                /** @var ResponseConfirmResult $result */
                $this->packetTypeByPubcomp($connectionId, $result);
                break;
            case ThreeProtocol::PACKET_TYPE_SUBSCRIBE:
                /** @var SubscribeResult $result */
                $this->packetTypeBySubscribe($connectionId, $result);
                break;
            case ThreeProtocol::PACKET_TYPE_UNSUBSCRIBE:
                /** @var UnSubscribeResult $result */
                $this->packetTypeByUnSubscribe($connectionId, $result);
                break;
            case ThreeProtocol::PACKET_TYPE_PINGREQ:
                $this->packetTypeByPingreq($connectionId);
                break;
            case ThreeProtocol::PACKET_TYPE_DISCONNECT:
                $this->packetTypeByDisconnect($clientId);
                break;
        }
    }

    /**
     * Closed handle
     *
     * @param string $connectionId
     */
    protected function closed(string $connectionId): void
    {
        $clientId = Socket::instance()->getClientId($connectionId);
        Socket::instance()->delete($connectionId);

        if ($clientId) {
            $clientInfo = Client::instance()->find($clientId);

            if ($clientInfo) {
                // MQTT-3.1.2-6
                if (Arr::get($clientInfo, 'will_flag', 1) == 1) {
                    $publishResult = new PublishResult();
                    $publishResult->setClientId($clientId);
                    $publishResult->setTopicName((string)Arr::get($clientInfo, 'will_topic'));
                    $publishResult->setContent((string)Arr::get($clientInfo, 'will_message'));
                    $publishResult->setQos((int)Arr::get($clientInfo, 'will_qos'));
                    $publishResult->setRetain((bool)Arr::get($clientInfo, 'will_retain'));

                    // subscribe
                    $this->swoole->task(new MatchTopicSubscribe($publishResult));
                }

                // MQTT-3.1.2-6
                if (Arr::get($clientInfo, 'clean_session', 1) == 1) {
                    Client::instance()->delete($clientId);
                }
            }

            // Emit close event
            $this->emit('close', $this, $clientId);
        }
    }

    /**
     * client single login
     *
     * @param string $connectionId
     * @param ConnectResult $connectResult
     */
    protected function clientSingle(string $connectionId, ConnectResult $connectResult): void
    {
        $clientInfo = Client::instance()->find($connectResult->getClientId());
        if ($clientInfo) {
            $cacheConnectionId = Arr::get($clientInfo, 'connection_id');

            if ($cacheConnectionId && $cacheConnectionId != $connectionId) {
                $this->log('force:' . $cacheConnectionId);
                $this->swoole->close($cacheConnectionId);
            }
        }

        Socket::instance()->add($connectionId, $connectResult->getClientId());

        Client::instance()->add($connectResult->getClientId(), [
            'connection_id' => $connectionId,
            'client_id' => $connectResult->getClientId(),
            'protocol_version' => $connectResult->getProtocolVersion(),
            'keep_alive' => $connectResult->getKeepAlive(),
            'clean_session' => (int)$connectResult->isCleanSession(),
            'will_flag' => (int)$connectResult->isWillFlag(),
            'will_retain' => (int)$connectResult->isWillRetain(),
            'will_qos' => $connectResult->getWillQos(),
            'will_topic' => (string)$connectResult->getWillTopic(),
            'will_message' => (string)$connectResult->getWillMessage(),
            'username_flag' => (int)$connectResult->isUsernameFlag(),
            'password_flag' => (int)$connectResult->isPasswordFlag(),
            'username' => (string)$connectResult->getUsername(),
        ]);
    }

    /**
     * mqtt heartbeat
     * MQTT-3.1.2-24
     */
    protected function keepAlive(): void
    {
        $time = time();
        foreach ($this->swoole->connections as $connectionId) {
            $clientId = Socket::instance()->getClientId($connectionId);
            if (!$clientId) {
                continue;
            }

            $clientInfo = Client::instance()->find($clientId);
            $keepAlive = (int)Arr::get($clientInfo, 'keep_alive');
            $keepAlive *= 1.5;


            $connectionInfo = $this->swoole->getClientInfo($connectionId);
            $lastTime = $connectionInfo ? $connectionInfo['last_time'] : 0;

            if ($keepAlive && $lastTime + $keepAlive < $time) {
                $this->close($clientId);
            }
        }
    }

    protected function packetTypeByConnect(string $connectionId, ConnectResult $result): void
    {
        // 授权回调事件
        $authorize = $this->emit('authorize', $this, $result->getClientId(), $result->getUsername(), $result->getPassword());
        if ($authorize === false) {
            $this->log('授权失败');

            $this->swoole->send($connectionId, ThreeProtocol::connack(false, ConnectException::USER_INVALID));
            $this->swoole->close($connectionId);
        } else {
            // 单点登录
            $this->clientSingle($connectionId, $result);

            $this->swoole->send($connectionId, ThreeProtocol::connack($result->isCleanSession() ? false : true, ThreeProtocol::CONNECT_RETURN_CODE_RECEIVE));
        }

        // 连接成功后事件
        $this->emit('connected', $this, $result->getClientId());
    }

    protected function packetTypeByPublish(string $connectionId, PublishResult $result): void
    {
        if ($result->getQos() == ThreeProtocol::QOS1) {
            $this->swoole->send($connectionId, ThreeProtocol::puback($result->getMessageId()));
        } else if ($result->getQos() == ThreeProtocol::QOS2) {
            $this->swoole->send($connectionId, ThreeProtocol::pubrec($result->getMessageId()));
        }

        // subscribe
        $this->swoole->task(new MatchTopicSubscribe($result));

        // TODO QOS2应该要确认后才能触发，这里在考虑要不要保存信息
        // Emit messaged event
        $this->emit('message', $this, $result->getClientId(), $result->getTopicName(), $result->getContent());
    }

    protected function packetTypeByPuback(string $connectionId, ResponseConfirmResult $result): void
    {
        // MQTT-2.3.1-6
        if (MessageId::instance()->exists($result->getClientId(), $result->getMessageId() ?: '') == false) {
            $this->log('puback:messageId illegal');
            $this->close($result->getClientId());
        }
    }

    protected function packetTypeByPubrec(string $connectionId, ResponseConfirmResult $result): void
    {
        // MQTT-2.3.1-6
        if (MessageId::instance()->exists($result->getClientId(), $result->getMessageId() ?: '', false) == false) {
            $this->log('pubrec:messageId illegal');
            $this->close($result->getClientId());
            return;
        }

        $this->swoole->send($connectionId, ThreeProtocol::pubrel($result->getMessageId()));
    }

    protected function packetTypeByPubrel(string $connectionId, ResponseConfirmResult $result): void
    {
        $this->swoole->send($connectionId, ThreeProtocol::pubcomp($result->getMessageId()));
    }

    protected function packetTypeByPubcomp(string $connectionId, ResponseConfirmResult $result): void
    {
        // MQTT-2.3.1-6
        if (MessageId::instance()->exists($result->getClientId(), $result->getMessageId() ?: '') == false) {
            $this->log('pubcomp:messageId illegal');
            $this->close($result->getClientId());
            return;
        }
    }

    protected function packetTypeBySubscribe(string $connectionId, SubscribeResult $result): void
    {
        $res = [];
        foreach ($result->getSubscribes() as $k => $subscribe) {
            if (ThreeProtocol::checkSubscribe($subscribe['topic_filter'], $subscribe['qos'])) {
                TopicFilter::instance()->add($subscribe['topic_filter'], $result->getClientId(), $subscribe['qos'], $subscribe['qos']);

                $res[$k] = $subscribe['qos'];
            } else {
                $res[$k] = ThreeProtocol::SUBACK_CODE_FAIL;
            }
        }

        $this->swoole->send($connectionId, ThreeProtocol::suback($result->getMessageId(), $res));
    }

    protected function packetTypeByUnSubscribe(string $connectionId, UnSubscribeResult $result): void
    {
        foreach ($result->getSubscribes() as $k => $topicFilter) {
            TopicFilter::instance()->unSubscribe($topicFilter, $result->getClientId());
        }

        $this->swoole->send($connectionId, ThreeProtocol::unsuback($result->getMessageId()));
    }

    protected function packetTypeByPingreq(string $connectionId): void
    {
        $this->swoole->send($connectionId, ThreeProtocol::pingresp());
    }

    protected function packetTypeByDisconnect(string $clientId): void
    {
        $this->log('clientId logout');
        $this->close($clientId);
    }
}
