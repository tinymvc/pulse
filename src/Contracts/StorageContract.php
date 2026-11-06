<?php

namespace Spark\Pulse\Contracts;

use Spark\Pulse\Entry;

/**
 * Storage Contract for Spark Pulse telemetry records.
 *
 * @package Spark\Pulse\Contracts
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
interface StorageContract
{
    /**
     * Store one or more telemetry entries.
     *
     * @param Entry[]|Entry $entries
     * @return void
     */
    public function store(array|Entry $entries): void;

    /**
     * Retrieve entries by type with pagination and filtering options.
     *
     * @param string $type
     * @param array $options [limit, before, search, family_hash, batch_id, slow_only]
     * @return array{entries: array, total: int, has_more: bool}
     */
    public function get(string $type, array $options = []): array;

    /**
     * Find a single entry by its ID.
     *
     * @param string $id
     * @return array|null
     */
    public function find(string $id): ?array;

    /**
     * Get all entries sharing a given batch trace ID.
     *
     * @param string $batchId
     * @return array
     */
    public function getByBatch(string $batchId): array;

    /**
     * Count entries of a specific type.
     *
     * @param string|null $type
     * @param string|null $since ISO timestamp
     * @return int
     */
    public function count(?string $type = null, ?string $since = null): int;

    /**
     * Get aggregated counts grouped by entry type.
     *
     * @param string|null $since ISO timestamp
     * @return array<string, int>
     */
    public function counts(?string $since = null): array;

    /**
     * Prune entries older than specified hours.
     *
     * @param int $hours
     * @return int Number of pruned entries
     */
    public function prune(int $hours): int;

    /**
     * Clear all recorded entries.
     *
     * @return void
     */
    public function clear(): void;
}
