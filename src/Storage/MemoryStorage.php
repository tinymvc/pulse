<?php

namespace Spark\Pulse\Storage;

use Spark\Pulse\Contracts\StorageContract;
use Spark\Pulse\Entry;
use function array_key_exists;
use function array_slice;
use function count;
use function is_array;

/**
 * In-Memory Storage Driver for Spark Pulse.
 *
 * Useful for automated unit testing or ephemeral environments without database access.
 *
 * @package Spark\Pulse\Storage
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class MemoryStorage implements StorageContract
{
    /** @var array<string, array> In-memory store */
    private array $entries = [];

    /**
     * {@inheritdoc}
     */
    public function store(array|Entry $entries): void
    {
        $entries = is_array($entries) ? $entries : [$entries];
        foreach ($entries as $entry) {
            if ($entry instanceof Entry) {
                $this->entries[$entry->id] = $entry->toArray();
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $type, array $options = []): array
    {
        $filtered = array_filter($this->entries, function ($entry) use ($type, $options) {
            if ($entry['type'] !== $type) {
                return false;
            }

            if (!empty($options['before']) && $entry['created_at'] >= $options['before']) {
                return false;
            }

            if (!empty($options['batch_id']) && $entry['batch_id'] !== $options['batch_id']) {
                return false;
            }

            if (!empty($options['family_hash']) && ($entry['family_hash'] ?? null) !== $options['family_hash']) {
                return false;
            }

            if (!empty($options['slow_only'])) {
                $threshold = (float) ($options['slow_threshold'] ?? 500);
                if (($entry['duration'] ?? 0) < $threshold) {
                    return false;
                }
            }

            if (!empty($options['search'])) {
                $contentStr = json_encode($entry['content']);
                if (!str_contains($contentStr, $options['search'])) {
                    return false;
                }
            }

            return true;
        });

        // Sort descending by created_at
        usort($filtered, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));

        $total = count($filtered);
        $limit = max(1, min((int) ($options['limit'] ?? 50), 200));
        $sliced = array_slice($filtered, 0, $limit);

        return [
            'entries' => array_values($sliced),
            'total' => $total,
            'has_more' => $total > $limit,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function find(string $id): ?array
    {
        return $this->entries[$id] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    public function getByBatch(string $batchId): array
    {
        $matches = array_filter($this->entries, fn($e) => ($e['batch_id'] ?? null) === $batchId);
        usort($matches, fn($a, $b) => strcmp($a['created_at'], $b['created_at']));

        return array_values($matches);
    }

    /**
     * {@inheritdoc}
     */
    public function count(?string $type = null, ?string $since = null): int
    {
        if ($type === null && $since === null) {
            return count($this->entries);
        }

        $filtered = array_filter($this->entries, function ($e) use ($type, $since) {
            if ($type !== null && $e['type'] !== $type) {
                return false;
            }
            if ($since !== null && $e['created_at'] < $since) {
                return false;
            }
            return true;
        });

        return count($filtered);
    }

    /**
     * {@inheritdoc}
     */
    public function counts(?string $since = null): array
    {
        $counts = array_fill_keys(\Spark\Pulse\EntryType::all(), 0);

        foreach ($this->entries as $entry) {
            $type = (string) ($entry['type'] ?? '');
            if (!array_key_exists($type, $counts)) {
                continue;
            }

            if ($since !== null && ($entry['created_at'] ?? '') < $since) {
                continue;
            }

            $counts[$type]++;
        }

        return $counts;
    }

    /**
     * {@inheritdoc}
     */
    public function prune(int $hours): int
    {
        $cutoff = gmdate('Y-m-d\TH:i:s.v\Z', time() - ($hours * 3600));
        $beforeCount = count($this->entries);

        $this->entries = array_filter($this->entries, fn($e) => $e['created_at'] >= $cutoff);

        return $beforeCount - count($this->entries);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $this->entries = [];
    }

    /**
     * {@inheritdoc}
     */
    public function ensureInstalled(): void
    {
        // No-op for memory storage
    }
}
