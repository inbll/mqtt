<?php

namespace Inbll\Mqtt\Database\Models;

class TopicFilter extends Model
{
    protected $table = 'topic_filters';

    protected $subscribeTable = 'topic_filter_%s_subscribes';


    /**
     * @param string $topicFilter
     * @param string $clientId
     * @param int $qos
     * @param int $originalQos
     */
    public function add(string $topicFilter, string $clientId, int $qos, int $originalQos): void
    {
        // 订阅主表
        if ($this->db()->exists($this->table, $topicFilter) == false) {
            $this->db()->insert($this->table, $topicFilter, [
                'topic_filter' => $topicFilter,
                'created_at' => time()
            ]);
        }

        // 客户端关注
        $data = [
            'client_id' => $clientId,
            'qos' => $qos,
            'original_qos' => $originalQos,
        ];
        $table = sprintf($this->subscribeTable, $topicFilter);
        if ($this->db()->exists($table, $clientId)) {
            $this->db()->update($table, $clientId, $data);
        } else {
            $this->db()->insert($table, $clientId, array_merge($data, [
                'subscribed_at' => time()
            ]));
        }
    }

    /**
     * @param string $topicFilter
     * @param string $clientId
     */
    public function unSubscribe(string $topicFilter, string $clientId): void
    {
        $table = sprintf($this->subscribeTable, $topicFilter);
        $this->db()->delete($table, $clientId);

        // 订阅主表
        if ($this->db()->count($table) == 0) {
            $this->db()->delete($this->table, $topicFilter);
        }
    }

    /**
     * @return array
     */
    public function get(): array
    {
        return $this->db()->get($this->table);
    }

    /**
     * @param string $topicFilter
     * @return array
     */
    public function getSubscribes(string $topicFilter): array
    {
        return $this->db()->get(sprintf($this->subscribeTable, $topicFilter));
    }
}