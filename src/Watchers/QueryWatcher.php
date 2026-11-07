<?php

namespace Spark\Pulse\Watchers;

use Spark\Pulse\Entry;
use Spark\Pulse\EntryType;
use Spark\Pulse\Storage\DatabaseStorage;
use Spark\Telemetry;
use function is_array;
use function is_int;
use function is_string;

/**
 * Database Query Watcher.
 *
 * Records SQL statements executed across database connections,
 * bindings, execution durations, slow query alerts, and duplicate N+1 detection.
 *
 * @package Spark\Pulse\Watchers
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class QueryWatcher extends Watcher
{
    /** @var array<string, int> Track queries within active batch to detect duplicates */
    private static array $seenQueries = [];

    private static ?string $batch = null;

    public function __construct()
    {
        parent::__construct('queries');
    }

    protected function boot(): void
    {
        Telemetry::listen('db.queryExecuted', function (string|array $sql, ?float $time = null, array|int $bindings = [], int|string|null $memory = null, string $connection = 'default') {
            $after = memory_get_usage(true);
            // The query builder supplies bindings before the memory sample. Direct DB calls omit them.
            if (is_array($sql)) {
                $bindings = $sql['bindings'] ?? [];
                $time = $sql['time'] ?? null;
                $memory = $sql['memory_before'] ?? null;
                $connection = $sql['connection'] ?? 'default';
                $sql = (string) ($sql['query'] ?? '');
            } elseif (is_int($bindings)) {
                $connection = is_string($memory) ? $memory : 'default';
                $memory = $bindings;
                $bindings = [];
            }

            if ($sql === '' || DatabaseStorage::isQuerying() || !$this->pulse->shouldRecord()) {
                return;
            }
            if ($this->isExcluded($sql, $connection)) {
                return;
            }

            // Format family hash by normalizing numeric/quoted literals in SQL
            $normalizedSql = preg_replace('/[0-9]+|\'[^\']*\'/', '?', $sql);
            $familyHash = md5($normalizedSql);

            // Duplicate detection within current request trace
            $batchId = \Spark\Pulse\Pulse::getBatchId();
            if ($batchId !== self::$batch) {
                self::$batch = $batchId;
                self::resetDuplicates();
            }
            $duplicateCount = 1;
            if (config('pulse.watchers.queries.detect_duplicates', true)) {
                $key = md5("$connection:$sql");
                $duplicateCount = self::$seenQueries[$key] = (self::$seenQueries[$key] ?? 0) + 1;
            }
            $isDuplicate = $duplicateCount > 1;

            $slowThreshold = (float) config('pulse.slow_thresholds.query', 50);
            $isSlow = $time !== null && $time >= $slowThreshold;

            $tags = [$connection];
            if ($isSlow) {
                $tags[] = 'slow';
            }
            if ($isDuplicate) {
                $tags[] = 'duplicate';
            }

            $entry = Entry::make(EntryType::QUERY, [
                'sql' => $sql,
                'bindings' => config('pulse.watchers.queries.record_bindings', false) ? $bindings : [],
                'duration_ms' => $time,
                ...$this->allocation(is_int($memory) ? $memory : null, $after),
                'connection' => $connection,
                'is_duplicate' => $isDuplicate,
                'duplicate_count' => $duplicateCount,
            ])
                ->duration($time)
                ->familyHash($familyHash)
                ->tags($tags);

            $this->record($entry);
        }, priority: PHP_INT_MAX);
    }

    /**
     * Clear duplicate tracking cache.
     */
    public static function resetDuplicates(): void
    {
        self::$seenQueries = [];
    }
}
