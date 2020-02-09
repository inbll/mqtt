<?php

namespace Inbll\Mqtt\Database\Models;

/**
 * Class MessageId
 * @package Inbll\Mqtt\Database\Models
 */
class MessageId extends Model
{
    protected $table = '%s_message_ids';
    
    
    /**
     * @param string $clientId
     * @param int $recursion
     * @return int
     * @throws \Exception
     */
    public function create(string $clientId, int $recursion = 10): int
    {
        $messageId = mt_rand(1, 65535);

        // 检测是否已有该消息ID,有的话则递归获取
        if ($this->exists($clientId, $messageId)) {
            if ($recursion > 0) {
                return $this->create($clientId, $recursion - 1);
            } else {
                throw new \Exception('Failed to build message_id!');
            }
        }

        $this->db()->keyInsert($this->getTable($clientId), $messageId);

        return $messageId;
    }

    /**
     * @param string $clientId
     * @param int $messageId
     * @param bool $forget
     * @return bool
     */
    public function exists(string $clientId, int $messageId, bool $forget = true): bool
    {
        $exists = $this->db()->exists($this->getTable($clientId), $messageId);
        if ($exists && $forget) {
            $this->delete($clientId, $messageId);
        }

        return $exists;
    }

    /**
     * @param string $clientId
     * @param int $messageId
     */
    public function delete(string $clientId, int $messageId): void
    {
        $this->db()->delete($this->getTable($clientId), $messageId);
    }

    /**
     * @param int|array $clientId
     */
    public function truncate($clientId): void
    {
        if (!is_array($clientId)) {
            $clientId = (array)$clientId;
        }

        foreach ($clientId as $value) {
            $this->db()->truncate($this->getTable($value));
        }
    }

    /**
     * @param string $clientId
     * @return string
     */
    protected function getTable(string $clientId): string
    {
        return sprintf($this->table, $clientId);
    }
}