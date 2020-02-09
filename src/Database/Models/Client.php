<?php

namespace Inbll\Mqtt\Database\Models;

/**
 * Class Client
 * @package Inbll\Mqtt\Database\Models
 */
class Client extends Model
{
    protected $table = 'clients';

    protected $idTable = 'client_ids';

    /**
     * All clientId
     *
     * @return array
     */
    public function getIds(): array
    {
        return $this->db()->get($this->idTable, false);
    }

    /**
     * @param string $clientId
     * @return array
     */
    public function find(string $clientId): array
    {
        return $this->db()->find($this->table, $clientId) ?: [];
    }

    /**
     * @param string $clientId
     * @param array $data
     */
    public function add(string $clientId, array $data): void
    {
        $this->db()->keyInsert($this->idTable, $clientId);
        $this->db()->insert($this->table, $clientId, $data);
    }

    /**
     * @param string $clientId
     */
    public function delete(string $clientId): void
    {
        $this->db()->delete($this->idTable, $clientId);
        $this->db()->delete($this->table, $clientId);

        MessageId::instance()->truncate($clientId);
    }
}