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
        $this->pulse->record($entry);
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
        if ($sensitiveKeys !== null) {
            $sensitiveMap = array_fill_keys(array_map('strtolower', $sensitiveKeys), true);
            return $this->maskRecursive($data, $sensitiveMap);
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
            static::$sensitiveMap = array_fill_keys(array_map('strtolower', $keys), true);
        }

        return $this->maskRecursive($data, static::$sensitiveMap);
    }

    private function maskRecursive(array $data, array $sensitiveMap): array
    {
        foreach ($data as $key => $value) {
            $keyLower = strtolower((string) $key);
            if (isset($sensitiveMap[$keyLower])) {
                $data[$key] = '********';
            } elseif (is_array($value)) {
                $data[$key] = $this->maskRecursive($value, $sensitiveMap);
            }
        }
        return $data;
    }
}
