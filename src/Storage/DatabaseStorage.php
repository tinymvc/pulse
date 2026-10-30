<?php

namespace Spark\Pulse\Storage;

use Spark\Database\Schema\Blueprint;
use Spark\Facades\DB;
use Spark\Facades\Schema;
use Spark\Pulse\Contracts\StorageContract;
use Spark\Pulse\Entry;
use function count;
use function is_array;
use function is_string;

/**
 * Database Storage Driver for Spark Pulse.
 *
 * Persists entries into a database table with batch ingestion support,
 * fast indexed lookups, and auto-creation.
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

    /** @var bool Whether installation has been verified for this instance */
    private bool $installed = false;

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
    protected function query()
    {
        return DB::table($this->table);
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

        $this->ensureInstalled();

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
                'content' => json_encode($entry->content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'tags' => !empty($entry->tags) ? json_encode($entry->tags) : null,
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
        $this->ensureInstalled();

        $limit = max(1, min((int) ($options['limit'] ?? 50), 200));
        $builder = $this->query()->where('type', $type);

        if (!empty($options['before'])) {
            $builder->where('created_at', '<', $options['before']);
        }

        if (!empty($options['batch_id'])) {
            $builder->where('batch_id', $options['batch_id']);
        }

        if (!empty($options['family_hash'])) {
            $builder->where('family_hash', $options['family_hash']);
        }

        if (!empty($options['search'])) {
            $builder->where('content', 'LIKE', '%' . $options['search'] . '%');
        }

        if (!empty($options['slow_only'])) {
            $threshold = (float) ($options['slow_threshold'] ?? 500);
            $builder->where('duration', '>=', $threshold);
        }

        $total = (clone $builder)->count();

        $records = $builder->orderBy('created_at', 'DESC')
            ->limit($limit + 1)
            ->get();

        $rows = is_array($records) ? $records : (method_exists($records, 'all') ? $records->all() : (array) $records);
        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }

        $formatted = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            $row['content'] = is_string($row['content'] ?? null) ? json_decode($row['content'], true) : ($row['content'] ?? []);
            $row['tags'] = is_string($row['tags'] ?? null) ? json_decode($row['tags'], true) : ($row['tags'] ?? []);
            $formatted[] = $row;
        }

        return [
            'entries' => $formatted,
            'total' => $total,
            'has_more' => $hasMore,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function find(string $id): ?array
    {
        $this->ensureInstalled();

        $record = $this->query()->where('id', $id)->first();
        if (!$record) {
            return null;
        }

        $record = (array) $record;
        $record['content'] = is_string($record['content'] ?? null) ? json_decode($record['content'], true) : ($record['content'] ?? []);
        $record['tags'] = is_string($record['tags'] ?? null) ? json_decode($record['tags'], true) : ($record['tags'] ?? []);

        return $record;
    }

    /**
     * {@inheritdoc}
     */
    public function getByBatch(string $batchId): array
    {
        $this->ensureInstalled();

        $records = $this->query()
            ->where('batch_id', $batchId)
            ->orderBy('created_at', 'ASC')
            ->get();

        $rows = is_array($records) ? $records : (method_exists($records, 'all') ? $records->all() : (array) $records);
        $formatted = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            $row['content'] = is_string($row['content'] ?? null) ? json_decode($row['content'], true) : ($row['content'] ?? []);
            $row['tags'] = is_string($row['tags'] ?? null) ? json_decode($row['tags'], true) : ($row['tags'] ?? []);
            $formatted[] = $row;
        }

        return $formatted;
    }

    /**
     * {@inheritdoc}
     */
    public function count(?string $type = null, ?string $since = null): int
    {
        $this->ensureInstalled();

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
    public function prune(int $hours): int
    {
        $this->ensureInstalled();

        $cutoff = gmdate('Y-m-d\TH:i:s.v\Z', time() - ($hours * 3600));

        return (int) $this->query()
            ->where('created_at', '<', $cutoff)
            ->delete();
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $this->ensureInstalled();

        $this->query()->truncate();
    }

    /**
     * {@inheritdoc}
     */
    public function ensureInstalled(): void
    {
        if ($this->installed) {
            return;
        }

        try {
            if (!Schema::hasTable($this->table)) {
                Schema::create($this->table, function (Blueprint $table) {
                    $table->string('id', 36)->primary();
                    $table->string('batch_id', 36)->index();
                    $table->string('type', 30)->index();
                    $table->string('family_hash', 64)->nullable()->index();
                    $table->float('duration')->nullable()->index();
                    $table->longText('content');
                    $table->text('tags')->nullable();
                    $table->string('created_at', 30)->index();
                });
            }
            $this->installed = true;
        } catch (\Throwable $e) {
            // Log and allow subsequent attempts
            tracer_log('Pulse database table creation check failed: ' . $e->getMessage());
        }
    }
}
