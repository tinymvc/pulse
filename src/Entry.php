<?php

namespace Spark\Pulse;

use JsonSerializable;
use function in_array;

/**
 * Normalized telemetry data transfer object for Spark Pulse.
 *
 * @package Spark\Pulse
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class Entry implements JsonSerializable
{
    /** @var string Unique entry identifier */
    public string $id;

    /** @var string Shared trace identifier correlating entries within one request/command */
    public string $batchId;

    /** @var string Telemetry type (from EntryType) */
    public string $type;

    /** @var string|null Grouping hash for recurring queries, exceptions, or routes */
    public ?string $familyHash = null;

    /** @var array Structured payload data */
    public array $content = [];

    /** @var float|null Execution time in milliseconds */
    public ?float $duration = null;

    /** @var array<string> Searchable tags */
    public array $tags = [];

    /** @var string ISO-8601 timestamp with microsecond accuracy */
    public string $createdAt;

    /**
     * Entry constructor.
     */
    public function __construct(string $type, array $content = [], ?string $batchId = null)
    {
        $this->id = bin2hex(random_bytes(16));
        $this->batchId = $batchId ?? Pulse::getBatchId();
        $this->type = $type;
        $this->content = $content;
        $this->createdAt = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.u\Z');
    }

    /**
     * Create a new telemetry entry.
     *
     * @param string $type
     * @param array $content
     * @param string|null $batchId
     * @return static
     */
    public static function make(string $type, array $content = [], ?string $batchId = null): static
    {
        return new static($type, $content, $batchId);
    }

    /**
     * Set the execution duration in milliseconds.
     *
     * @param float|null $duration
     * @return $this
     */
    public function duration(?float $duration): static
    {
        $this->duration = $duration;
        return $this;
    }

    /**
     * Set grouping family hash.
     *
     * @param string|null $familyHash
     * @return $this
     */
    public function familyHash(?string $familyHash): static
    {
        $this->familyHash = $familyHash;
        return $this;
    }

    /**
     * Add searchable tags.
     *
     * @param string|array $tags
     * @return $this
     */
    public function tags(string|array $tags): static
    {
        $tags = (array) $tags;
        foreach ($tags as $tag) {
            if (is_scalar($tag) && (string) $tag !== '' && !in_array((string) $tag, $this->tags, true)) {
                $this->tags[] = (string) $tag;
            }
        }
        return $this;
    }

    /**
     * Convert entry to an associative array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'batch_id' => $this->batchId,
            'type' => $this->type,
            'family_hash' => $this->familyHash,
            'content' => $this->content,
            'duration' => $this->duration,
            'tags' => $this->tags,
            'created_at' => $this->createdAt,
        ];
    }

    /** One-line description shared by the activity list and its execution trace. */
    public static function summary(array $entry): string
    {
        $c = array_filter($entry['content'], 'is_scalar');
        return (string) match ($entry['type']) {
            EntryType::REQUEST => trim(($c['method'] ?? '') . ' ' . ($c['uri'] ?? '/')),
            EntryType::HTTP_CLIENT => trim(($c['method'] ?? '') . ' ' . ($c['url'] ?? '')),
            EntryType::QUERY => $c['sql'] ?? 'Query',
            EntryType::EXCEPTION => ($c['class'] ?? 'Exception') . ' · ' . ($c['message'] ?? ''),
            EntryType::COMMAND => 'spark ' . ($c['name'] ?? $c['command'] ?? ''),
            EntryType::JOB, EntryType::EVENT => $c['name'] ?? ucfirst($entry['type']),
            EntryType::CACHE => ($c['action'] ?? 'Cache') . ' ' . ($c['key'] ?? implode(', ', array_filter((array) ($entry['content']['keys'] ?? []), 'is_scalar'))),
            EntryType::GATE => $c['ability'] ?? 'Gate',
            EntryType::LOG => $c['message'] ?? 'Log entry',
            EntryType::MAIL => $c['subject'] ?? 'Mail',
            EntryType::MODEL => ($c['action'] ?? '') . ' ' . ($c['model'] ?? 'Model'),
            EntryType::LANGUAGE_FILE => $c['path'] ?? 'Language file',
            EntryType::VIEW => $c['template'] ?? $c['path'] ?? 'View',
            EntryType::PERFORMANCE => 'Server snapshot',
            default => $entry['type'],
        };
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
