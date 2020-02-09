<?php

namespace Inbll\Mqtt\Database\Models;

/**
 * Class Socket
 * @package Inbll\Mqtt\Database\Models
 */
class Socket extends Model
{
    protected $table = 'connections';


    /**
     * @param string $connectionId
     * @param string $clientId
     */
    public function add(string $connectionId, string $clientId): void
    {
        $this->db()->insert($this->table, $connectionId, [
            'client_id' => $clientId
        ]);
    }

    /**
     * @param string $connectionId
     */
    public function delete(string $connectionId)
    {
        $this->db()->delete($this->table, $connectionId);
    }

    /**
     * @param string $connectionId
     * @return string|null
     */
    public function getClientId(string $connectionId): ?string
    {
        return $this->db()->value($this->table, $connectionId, 'client_id');
    }
}