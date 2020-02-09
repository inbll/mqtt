<?php

namespace Inbll\Mqtt\Tasks;

use Inbll\Mqtt\Contracts\TaskInterface;
use Inbll\Mqtt\Database\Models\Client;
use Inbll\Mqtt\Database\Models\MessageId;
use Inbll\Mqtt\Database\Models\TopicFilter;
use Inbll\Mqtt\Protocols\ThreeProtocol;
use Inbll\Mqtt\Results\PublishResult;
use Swoole\Server;

/**
 * Class TopicSubscribeDispatch
 * @package Inbll\Mqtt\Tasks
 */
class TopicSubscribeDispatch implements TaskInterface
{
    protected $publishResult;

    protected $topicFilter;


    public function __construct(PublishResult $publishResult, string $topicFilter)
    {
        $this->publishResult = $publishResult;
        $this->topicFilter = $topicFilter;
    }

    public function handle(Server $server)
    {
        $subscribeInfos = TopicFilter::instance()->getSubscribes($this->topicFilter);

        foreach ($subscribeInfos as $subscribeInfo) {
            $clientInfo = Client::instance()->find($subscribeInfo['client_id']);
            if (!$clientInfo) {
                continue;
            }

            $messageId = null;
            $qos = $this->publishResult->getQos() > $subscribeInfo['qos'] ? $subscribeInfo['qos'] : $this->publishResult->getQos();
            if ($qos) {
                $messageId = MessageId::instance()->create($subscribeInfo['client_id']);

                // MessageId destruct after 30 seconds
                $server->after(30000, function () use ($subscribeInfo, $messageId) {
                    MessageId::instance()->delete($subscribeInfo['client_id'], $messageId);
                });
            }

            $result = $server->send($clientInfo['connection_id'], ThreeProtocol::publish($this->publishResult->getTopicName(), $this->publishResult->getContent(), $messageId, $qos));
        }
    }

    public function getResult(): bool
    {
        return true;
    }
}