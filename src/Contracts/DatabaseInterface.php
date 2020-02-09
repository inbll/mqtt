<?php

namespace Inbll\Mqtt\Contracts;

/**
 * Interface DatabaseInterface
 * @package Inbll\Mqtt\Contracts
 */
interface DatabaseInterface
{
    /**
     * key insert
     *
     * @param string $table
     * @param string $key
     */
    public function keyInsert(string $table, string $key): void;

    /**
     * @param string $table
     * @param string $key
     * @param array $data
     * @return bool
     */
    public function insert(string $table, string $key, array $data): bool;

    /**
     * @param string $table
     * @param string $key
     * @param array $data
     * @return bool
     */
    public function update(string $table, string $key, array $data): bool;

    /**
     * @param string $table
     * @param bool $serialize
     * @return array
     */
    public function get(string $table, bool $serialize = true): array;

    /**
     * @param string $table
     * @param string $key
     * @return array|null
     */
    public function find(string $table, string $key): ?array;

    /**
     * @param string $table
     * @param string $key
     * @param string $field
     * @return mixed
     */
    public function value(string $table, string $key, string $field);

    /**
     * @param string $table
     * @param string $key
     */
    public function delete(string $table, string $key): void;

    /**
     * @param string $table
     * @param string $key
     * @return bool
     */
    public function exists(string $table, string $key): bool;

    /**
     * @param string $table
     * @return int
     */
    public function count(string $table): int;

    /**
     * @param string $table
     */
    public function truncate(string $table): void;
}