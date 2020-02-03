<?php

namespace Inbll\Mqtt\Contracts;

interface DatabaseInterface
{
    /**
     * @param string $table
     * @param $key
     */
    public function keyInsert(string $table, $key): void;

    /**
     * @param string $table
     * @param $key
     * @param $value
     */
    public function insert(string $table, $key, $value): void;

    public function get(string $table): array;

    public function find(string $table, $key);

    public function delete(string $table, $key): void;

    public function exists(string $table, $key): bool;

    public function truncate(string $table): void;
}