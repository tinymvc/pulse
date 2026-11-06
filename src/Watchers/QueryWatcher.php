<?php

namespace Spark\Pulse\Watchers;

use Spark\Pulse\Entry;
use Spark\Pulse\EntryType;
use Spark\Telemetry;

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

    public function __construct()
    {
        parent::__construct('queries');
    }

    protected function boot(): void
    {
        Telemetry::listen('db.queryExecuted', function (array $payload) {
            $sql = (string) ($payload['query'] ?? '');
            if ($sql === '') {
                return;
            }

            // Exclude internal Pulse table queries from being recursively logged
            $pulseTable = (string) config('pulse.storage.table', 'pulse_entries');
            if (str_contains($sql, $pulseTable)) {
                return;
            }

            $time = (float) ($payload['time'] ?? 0.0);
            $bindings = (array) ($payload['bindings'] ?? []);
            $connection = (string) ($payload['connection'] ?? 'default');

            // Format family hash by normalizing numeric/quoted literals in SQL
            $normalizedSql = preg_replace('/[0-9]+|\'[^\']*\'/', '?', $sql);
            $familyHash = md5($normalizedSql);

            // Duplicate detection within current request trace
            $batchId = \Spark\Pulse\Pulse::getBatchId();
            $duplicateKey = "$batchId:$sql";
            self::$seenQueries[$duplicateKey] = (self::$seenQueries[$duplicateKey] ?? 0) + 1;
            $isDuplicate = self::$seenQueries[$duplicateKey] > 1;

            $slowThreshold = (float) config('pulse.slow_thresholds.query', 50);
            $isSlow = $time >= $slowThreshold;

            $tags = [$connection];
            if ($isSlow) {
                $tags[] = 'slow';
            }
            if ($isDuplicate) {
                $tags[] = 'duplicate';
            }

            $entry = Entry::make(EntryType::QUERY, [
                'sql' => $sql,
                'bindings' => $this->mask($bindings),
                'duration_ms' => $time,
                'connection' => $connection,
                'is_duplicate' => $isDuplicate,
                'duplicate_count' => self::$seenQueries[$duplicateKey],
            ])
                ->duration($time)
                ->familyHash($familyHash)
                ->tags($tags);

            $this->record($entry);
        });
    }

    /**
     * Clear duplicate tracking cache.
     */
    public static function resetDuplicates(): void
    {
        self::$seenQueries = [];
    }
}
