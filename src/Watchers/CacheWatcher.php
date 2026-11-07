<?php

namespace Spark\Pulse\Watchers;

use Spark\Pulse\Entry;
use Spark\Pulse\EntryType;
use Spark\Telemetry;
use function array_slice;
use function count;
use function is_array;

/**
 * Cache Watcher.
 *
 * Records cache hits, misses, writes, forgets, and tracks hit ratios.
 *
 * @package Spark\Pulse\Watchers
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class CacheWatcher extends Watcher
{
    public function __construct()
    {
        parent::__construct('cache');
    }

    protected function boot(): void
    {
        Telemetry::listen('cache.hit', function (array $payload) {
            $key = (string) ($payload['key'] ?? '');
            $entry = Entry::make(EntryType::CACHE, [
                'action' => 'hit',
                'key' => $key,
                'store' => $payload['store'] ?? 'default',
                'value' => $this->previewValue($key, $payload['value'] ?? null),
            ])
                ->tags(['hit', $key]);

            $this->record($entry);
        });

        Telemetry::listen('cache.miss', function (array $payload) {
            $key = (string) ($payload['key'] ?? '');
            $entry = Entry::make(EntryType::CACHE, [
                'action' => 'miss',
                'key' => $key,
                'store' => $payload['store'] ?? 'default',
            ])
                ->tags(['miss', $key]);

            $this->record($entry);
        });

        Telemetry::listen('cache.written', function (array $payload) {
            $key = (string) ($payload['key'] ?? '');
            $entry = Entry::make(EntryType::CACHE, [
                'action' => 'written',
                'key' => $key,
                'store' => $payload['store'] ?? 'default',
                'expiration' => $payload['expiration'] ?? null,
                'value' => $this->previewValue($key, $payload['value'] ?? null),
            ])
                ->tags(['written', $key]);

            $this->record($entry);
        });

        Telemetry::listen('cache.forgotten', function (array $payload) {
            $keys = (array) ($payload['keys'] ?? []);
            $entry = Entry::make(EntryType::CACHE, [
                'action' => 'forgotten',
                'keys' => $keys,
                'store' => $payload['store'] ?? 'default',
            ])
                ->tags(['forgotten']);

            $this->record($entry);
        });
    }

    private function previewValue(string $key, mixed $value): mixed
    {
        if (is_array($value) && count($value) > 10) {
            $value = array_slice($value, 0, 10, true) + ['...more' => count($value) - 10];
        }
        return $this->mask([$key => $value])[$key];
    }
}
