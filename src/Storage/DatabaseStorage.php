<?php

namespace Spark\Pulse\Storage;

use Spark\Database\QueryBuilder;
use Spark\Facades\DB;
use Spark\Pulse\Contracts\StorageContract;
use Spark\Pulse\Entry;
use function count;
use function is_array;
use function is_string;

/**
 * Database Storage Driver for Spark Pulse.
 *
 * Persists entries into a database table with batch ingestion support,
 * indexed lookups and cursor-based paging.
 *
 * @package Spark\Pulse\Storage
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class DatabaseStorage implements StorageContract
{
    /** @var string Table name */
    private string $table;

    /** @var string|null Specific database connection */
    private ?string $connection;

    private ?\Spark\Database\DB $database = null;

    /**
     * DatabaseStorage constructor.
     */
    public function __construct(?string $table = null, ?string $connection = null)
    {
        $this->table = $table ?? (string) config('pulse.storage.table', 'pulse_entries');
        $this->connection = $connection ?? config('pulse.storage.connection');
    }

    /**
     * Get a query builder instance for the entries table.
     */
    protected function query(): QueryBuilder
    {
        $this->database ??= DB::connection($this->connection);
        return $this->database->table($this->table);
    }

    /**
     * {@inheritdoc}
     */
    public function store(array|Entry $entries): void
    {
        $entries = is_array($entries) ? $entries : [$entries];
        if (empty($entries)) {
            return;
        }

        $rows = [];
        foreach ($entries as $entry) {
            if (!$entry instanceof Entry) {
                continue;
            }

            $rows[] = [
                'id' => $entry->id,
                'batch_id' => $entry->batchId,
                'type' => $entry->type,
                'family_hash' => $entry->familyHash,
                'duration' => $entry->duration,
                'content' => json_encode($entry->content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                'tags' => !empty($entry->tags) ? json_encode($entry->tags, JSON_INVALID_UTF8_SUBSTITUTE) : null,
                'created_at' => $entry->createdAt,
            ];
        }

        if (!empty($rows)) {
            try {
                // Batch insert into the database table
                $this->query()->insert($rows);
            } catch (\Throwable $e) {
                // Ensure telemetry write failure never breaks the application
                tracer_log('Pulse storage write failed: ' . $e->getMessage());
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $type, array $options = []): array
    {
        $limit = max(1, min((int) ($options['limit'] ?? 50), 200));
        $builder = $this->query()->where('type', $type);

        if (!empty($options['before'])) {
            if (!empty($options['before_id'])) {
                $builder->where(function ($cursor) use ($options) {
                    $cursor->where('created_at', '<', $options['before'])
                        ->orWhere(function ($tie) use ($options) {
                            $tie->where('created_at', '=', $options['before'])->where('id', '<', $options['before_id']);
                        });
                });
            } else {
                $builder->where('created_at', '<', $options['before']);
            }
        }

        if (!empty($options['batch_id'])) {
            $builder->where('batch_id', $options['batch_id']);
        }

        if (!empty($options['family_hash'])) {
            $builder->where('family_hash', $options['family_hash']);
        }

        if (($options['search'] ?? '') !== '') {
            $term = strtr($options['search'], ['!' => '!!', '%' => '!%', '_' => '!_']);
            $builder->whereRaw("LOWER(content) LIKE LOWER(:pulse_search) ESCAPE '!'", ['pulse_search' => "%$term%"]);
        }

        if (!empty($options['slow_only'])) {
            $threshold = (float) ($options['slow_threshold'] ?? config("pulse.slow_thresholds.$type", 500));
            $builder->where('duration', '>=', $threshold);
        }

        $total = (clone $builder)->count();

        $rows = $builder->orderBy('created_at', 'DESC')
            ->orderBy('id', 'DESC')
            ->limit($limit + 1)
            ->all();

        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }

        return [
            'entries' => $this->formatRows($rows),
            'total' => $total,
            'has_more' => $hasMore,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function find(string $id): ?array
    {
        $record = $this->query()->where('id', $id)->first();
        if (!$record) {
            return null;
        }

        return $this->formatRow($record);
    }

    /**
     * {@inheritdoc}
     */
    public function getByBatch(string $batchId): array
    {
        $records = $this->query()
            ->where('batch_id', $batchId)
            ->orderBy('created_at', 'ASC')
            ->orderBy('id', 'ASC')
            ->all();

        return $this->formatRows($records);
    }

    /**
     * {@inheritdoc}
     */
    public function count(?string $type = null, ?string $since = null): int
    {
        $builder = $this->query();
        if ($type !== null) {
            $builder->where('type', $type);
        }
        if ($since !== null) {
            $builder->where('created_at', '>=', $since);
        }

        return (int) $builder->count();
    }

    /**
     * {@inheritdoc}
     */
    public function counts(?string $since = null): array
    {
        $builder = $this->query()
            ->select(['type', DB::raw('COUNT(*) as total')])
            ->groupBy('type');

        if ($since !== null) {
            $builder->where('created_at', '>=', $since);
        }

        $rows = $builder->all();
        $counts = array_fill_keys(\Spark\Pulse\EntryType::all(), 0);
        foreach ($rows as $row) {
            $row = (array) $row;
            $type = (string) ($row['type'] ?? '');
            if ($type !== '' && array_key_exists($type, $counts)) {
                $counts[$type] = (int) ($row['total'] ?? 0);
            }
        }

        return $counts;
    }

    /**
     * {@inheritdoc}
     */
    public function prune(int $hours): int
    {
        $cutoff = gmdate('Y-m-d\TH:i:s.000000\Z', time() - ($hours * 3600));

        return (int) $this->query()
            ->where('created_at', '<', $cutoff)
            ->delete();
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $this->query()->truncate();
    }

    /**
     * Format a single database row into a structured entry array.
     *
     * @param array|object $row
     * @return array
     */
    protected function formatRow(array|object $row): array
    {
        $row = (array) $row;
        $row['content'] = is_string($row['content'] ?? null)
            ? (json_decode($row['content'], true) ?: [])
            : ($row['content'] ?? []);
        $row['tags'] = is_string($row['tags'] ?? null)
            ? (json_decode($row['tags'], true) ?: [])
            : ($row['tags'] ?? []);

        return $row;
    }

    /**
     * Format multiple database records.
     *
     * @param iterable $rows
     * @return array
     */
    protected function formatRows(iterable $rows): array
    {
        $formatted = [];
        foreach ($rows as $row) {
            $formatted[] = $this->formatRow($row);
        }

        return $formatted;
    }
}
