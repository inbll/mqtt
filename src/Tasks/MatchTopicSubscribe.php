<?php

namespace Inbll\Mqtt\Tasks;

use Inbll\Mqtt\Contracts\TaskInterface;
use Inbll\Mqtt\Database\Models\TopicFilter;
use Inbll\Mqtt\Protocols\ThreeProtocol;
use Inbll\Mqtt\Results\PublishResult;
use Swoole\Server;

/**
 * Class MatchTopicSubscribe
 * @package Inbll\Mqtt\Tasks
 */
class MatchTopicSubscribe implements TaskInterface
{
    public $publishResult;

    protected $result;

    public function __construct(PublishResult $publishResult)
    {
        $this->publishResult = $publishResult;
    }

    public function handle(Server $server): void
    {
        // TODO Need to optimize

        $topicFilters = TopicFilter::instance()->get();
        $matches = [];
        foreach ($topicFilters as $topicFilterData) {
            if (ThreeProtocol::matchTopicSubscribe($this->publishResult->getTopicName(), $topicFilterData['topic_filter'])) {
                $matches[] = $topicFilterData['topic_filter'];
            }
        }

        $this->result = $matches;
    }

    public function getResult(): array
    {
        return $this->result;
    }
}