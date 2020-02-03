<?php

namespace Inbll\Mqtt\Traits;

use Inbll\Mqtt\Databases\DatabaseManager;

trait MessageId
{
    /**
     * 消息集合KEY
     */
    private $messageIdKey = '%s_message_ids';
    
    
    /**
     * 生成消息ID
     *
     * @param string $clientId
     * @param int $recursion
     * @return int
     * @throws \Exception
     */
    protected function buildMessageId(string $clientId, int $recursion = 10): int
    {
        $messageId = mt_rand(1, 65535);

        // 检测是否已有该消息ID,有的话则递归获取
        if ($this->hasMessageId($clientId, $messageId)) {
            if ($recursion > 0) {
                return $this->buildMessageId($clientId, $recursion - 1);
            } else {
                throw new \Exception('Failed to build message_id!');
            }
        }

        DatabaseManager::connection()->keyInsert($this->getTable($clientId), $messageId);

        return $messageId;
    }

    /**
     * 检测消息ID是否存在
     *
     * @param string $clientId
     * @param int $messageId
     * @return bool
     */
    protected function hasMessageId(string $clientId, int $messageId): bool
    {
        return DatabaseManager::connection()->exists($this->getTable($clientId), $messageId);
    }

    protected function deleteMessageId(string $clientId, int $messageId): void
    {
        DatabaseManager::connection()->delete($this->getTable($clientId), $messageId);
    }

    /**
     * 清空某个用户的消息ID
     *
     * @param int|array $clientId
     */
    protected function clearMessageId($clientId): void
    {
        if (!is_array($clientId)) {
            $clientId = (array)$clientId;
        }

        foreach ($clientId as $value) {
            DatabaseManager::connection()->truncate($this->getTable($value));
        }
    }

    /**
     * 获取表名
     *
     * @param string $clientId
     * @return string
     */
    private function getTable(string $clientId): string
    {
        return sprintf($this->messageIdKey, $clientId);
    }
}