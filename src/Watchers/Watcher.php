<?php

namespace Spark\Pulse\Watchers;

use Spark\Pulse\Contracts\WatcherContract;
use Spark\Pulse\Entry;
use Spark\Pulse\Pulse;
use function is_array;

/**
 * Base Watcher implementation for Spark Pulse.
 *
 * @package Spark\Pulse\Watchers
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
abstract class Watcher implements WatcherContract
{
    /** @var Pulse */
    protected Pulse $pulse;

    /** @var string Config key identifier */
    protected string $key;

    /**
     * Watcher constructor.
     */
    public function __construct(string $key)
    {
        $this->key = $key;
    }

    /**
     * {@inheritdoc}
     */
    public function register(Pulse $pulse): void
    {
        $this->pulse = $pulse;

        if ($this->isEnabled()) {
            $this->boot();
        }
    }

    /**
     * Attach watcher-specific listeners and hooks.
     *
     * @return void
     */
    abstract protected function boot(): void;

    /**
     * {@inheritdoc}
     */
    public function isEnabled(): bool
    {
        return (bool) config("pulse.watchers.{$this->key}.enabled", true);
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

    /** @var array<string, bool>|null Cached sensitive keys lookup map */
    protected static ?array $sensitiveMap = null;

    /**
     * Reset cached sensitive keys map.
     */
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

    private function maskRecursive(array $data, array $sensitiveMap, int &$remaining, int $depth = 0): array
    {
        if ($depth >= 12) {
            return ['…'];
        }
        $preview = [];
        foreach ($data as $key => $value) {
            if ($remaining-- <= 0) {
                $preview['…'] = '[truncated]';
                break;
            }
            if (isset($sensitiveMap[$this->normalizeKey((string) $key)])) {
                $value = '********';
            } elseif (is_array($value)) {
                $value = $this->maskRecursive($value, $sensitiveMap, $remaining, $depth + 1);
            } elseif (is_object($value) || is_resource($value)) {
                $value = get_debug_type($value);
            } elseif (is_string($value)) {
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
}
