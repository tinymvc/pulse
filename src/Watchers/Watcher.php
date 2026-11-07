<?php

namespace Spark\Pulse\Watchers;

use DateTimeInterface;
use Spark\Carbon;
use Spark\Contracts\Support\Arrayable;
use Spark\Pulse\Contracts\WatcherContract;
use Spark\Pulse\Entry;
use Spark\Pulse\Pulse;
use Spark\Support\Collection;
use Spark\Support\LazyCollection;
use Spark\Support\Str;
use Spark\Url;
use Throwable;
use function is_array;
use function is_float;
use function is_int;
use function is_object;
use function is_resource;
use function is_string;
use function strlen;

/**
 * Base Watcher implementation for Spark Pulse.
 *
 * @package Spark\Pulse\Watchers
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
abstract class Watcher implements WatcherContract
{
    protected Pulse $pulse;

    /** @var array<string, bool>|null Cached sensitive keys lookup map */
    protected static ?array $sensitiveMap = null;

    public function __construct(protected string $key)
    {
    }

    public function register(Pulse $pulse): void
    {
        $this->pulse = $pulse;

        if ($this->isEnabled()) {
            $this->boot();
        }
    }

    abstract protected function boot(): void;

    public function isEnabled(): bool
    {
        return (bool) config("pulse.watchers.{$this->key}.enabled", true);
    }

    /** @return string[] */
    protected function exclusionPatterns(): array
    {
        return array_filter((array) config("pulse.watchers.{$this->key}.exclude", []), 'is_string');
    }

    /** Match any of this watcher's identifiers against its configured patterns. */
    protected function isExcluded(string ...$identifiers): bool
    {
        $patterns = $this->exclusionPatterns();
        if ($patterns === []) {
            return false;
        }

        foreach ($identifiers as $identifier) {
            if (Str::is($patterns, $identifier)) {
                return true;
            }
        }
        return false;
    }

    /** Reserved PHP memory at framework entry and on delivery of its completion event. */
    protected function allocation(?int $before, int $after): array
    {
        return $before === null ? [] : [
            'allocated_before_bytes' => $before,
            'allocated_after_bytes' => $after,
            'allocated_change_bytes' => $after - $before,
        ];
    }

    protected function isPathExcluded(string $path): bool
    {
        return $this->isExcluded($path, ltrim($path, '/'));
    }

    /**
     * Record a telemetry entry through Pulse.
     *
     * @param Entry $entry
     * @return void
     */
    protected function record(Entry $entry): void
    {
        if ($this->pulse->shouldRecord()) {
            $entry->content = $this->mask($entry->content);
            $this->pulse->record($entry);
        }
    }

    public static function resetSensitiveMap(): void
    {
        static::$sensitiveMap = null;
    }

    /**
     * Recursively mask sensitive keys in an array.
     *
     * @param array $data
     * @param array|null $sensitiveKeys
     * @return array
     */
    protected function mask(array $data, ?array $sensitiveKeys = null): array
    {
        $remaining = 1024;
        if ($sensitiveKeys !== null) {
            $sensitiveMap = array_fill_keys(array_map($this->normalizeKey(...), $sensitiveKeys), true);
            return $this->maskRecursive($data, $sensitiveMap, $remaining);
        }

        if (static::$sensitiveMap === null) {
            $keys = (array) config('pulse.sensitive_keys', [
                'password',
                'password_confirmation',
                'secret',
                'token',
                'authorization',
                'cookie',
                'api_key',
                'access_token',
                'app_key'
            ]);
            static::$sensitiveMap = array_fill_keys(array_map($this->normalizeKey(...), $keys), true);
        }

        return $this->maskRecursive($data, static::$sensitiveMap, $remaining);
    }

    private function normalizeKey(string $key): string
    {
        return str_replace('-', '_', strtolower(ltrim($key, '-:')));
    }

    /** Normalize wire-format header lines before applying the same key rules. */
    protected function headers(array $headers): array
    {
        $result = [];
        foreach ($headers as $key => $value) {
            if (is_int($key) && is_string($value) && str_contains($value, ':')) {
                [$key, $value] = explode(':', $value, 2);
                $value = trim($value);
            }
            $result[$key] = $value;
        }
        return $result;
    }

    /** @return ($data is array ? array : array|string) */
    private function maskRecursive(array|Arrayable $data, array $sensitiveMap, int &$remaining, int $depth = 0): array|string
    {
        if ($depth >= 12) {
            return ['…'];
        }
        if ($data instanceof Arrayable) {
            $data = $this->arrayablePreview($data);
            if (is_string($data)) {
                return $data;
            }
        }

        $preview = [];
        foreach ($data as $key => $value) {
            if ($remaining-- <= 0) {
                $preview['…'] = '[truncated]';
                break;
            }
            if (isset($sensitiveMap[$this->normalizeKey((string) $key)])) {
                $preview[$key] = '********';
                continue;
            }

            if ($value instanceof Carbon) {
                $value = $value->toIsoUtcString();
            } elseif ($value instanceof DateTimeInterface) {
                $value = Carbon::instance($value)->toIsoUtcString();
            } elseif ($value instanceof Url) {
                $value = $value->getUrl();
            } elseif (is_array($value) || $value instanceof Arrayable) {
                $value = $this->maskRecursive($value, $sensitiveMap, $remaining, $depth + 1);
            } elseif (is_object($value) || is_resource($value)) {
                $value = get_debug_type($value);
            } elseif (is_float($value) && !is_finite($value)) {
                $value = (string) $value;
            }
            if (is_string($value)) {
                if (strlen($value) > 8192) {
                    $value = substr($value, 0, 8192) . '…';
                }
                if (!preg_match('//u', $value)) {
                    $value = '[binary data]';
                }
            }
            $preview[$key] = $value;
        }
        return $preview;
    }

    private function arrayablePreview(Arrayable $value): array|string
    {
        // A lazy collection may wrap a one-shot or unbounded generator.
        if ($value instanceof LazyCollection) {
            return get_debug_type($value);
        }

        try {
            // Keep collection items intact so each nested key is masked before conversion.
            $items = $value instanceof Collection ? $value->all() : $value->toArray();
            return is_array($items) ? $items : get_debug_type($value);
        } catch (Throwable) {
            return get_debug_type($value);
        }
    }
}
