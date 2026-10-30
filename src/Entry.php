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
        $this->duration = $duration !== null ? round($duration, 2) : null;
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
            if ($tag && !in_array($tag, $this->tags, true)) {
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
