<?php

namespace Spark\Pulse;

use Spark\Foundation\Application;
use Spark\Http\Request;
use Spark\Pulse\Contracts\StorageContract;
use Spark\Pulse\Storage\DatabaseStorage;
use Spark\Pulse\Storage\MemoryStorage;
use Spark\Support\Traits\Macroable;
use function in_array;

/**
 * Spark Pulse Core Coordinator.
 *
 * Manages active trace IDs, telemetry buffering, filtering,
 * storage flushing, and dashboard authorization.
 *
 * @package Spark\Pulse
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class Pulse
{
    use Macroable;

    /** @var Pulse|null Singleton instance */
    public static ?Pulse $instance = null;

    /** @var string Current execution trace ID */
    protected static ?string $batchId = null;

    /** @var bool Whether Pulse is actively recording */
    protected bool $recording = true;

    /** @var Entry[] Ingestion buffer waiting to be flushed */
    protected array $entries = [];

    /** @var StorageContract Storage driver */
    protected StorageContract $storage;

    /** @var array<callable> Filter callbacks */
    protected array $filters = [];

    /** @var array<callable> Authorization callbacks */
    protected array $authCallbacks = [];

    /**
     * Pulse constructor.
     */
    public function __construct(?StorageContract $storage = null)
    {
        self::$instance = $this;
        $this->recording = (bool) config('pulse.enabled', true);

        $driver = config('pulse.storage.driver', 'database');
        $this->storage = $storage ?? match ($driver) {
            'memory' => new MemoryStorage(),
            default => new DatabaseStorage(),
        };

        // Ensure deferred flush runs on application termination
        $app = Application::$app;
        if ($app !== null) {
            $app->defer(fn() => $this->flush());
        }
    }

    /**
     * Get or create the singleton instance.
     *
     * @return static
     */
    public static function instance(): static
    {
        $app = Application::$app;
        if ($app !== null && $app->bound(Pulse::class)) {
            return $app->get(Pulse::class);
        }

        if (self::$instance === null) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    /**
     * Get the active trace batch ID, generating one if not set.
     *
     * @return string
     */
    public static function getBatchId(): string
    {
        if (self::$batchId === null) {
            self::$batchId = bin2hex(random_bytes(16));
        }
        return self::$batchId;
    }

    /**
     * Reset and generate a new batch ID.
     *
     * @return string
     */
    public static function newBatchId(): string
    {
        self::$batchId = bin2hex(random_bytes(16));
        return self::$batchId;
    }

    /**
     * Determine if Pulse is actively recording.
     *
     * @return bool
     */
    public function isRecording(): bool
    {
        return $this->recording;
    }

    /**
     * Start recording telemetry.
     */
    public function startRecording(): void
    {
        $this->recording = true;
    }

    /**
     * Temporarily pause recording telemetry.
     */
    public function stopRecording(): void
    {
        $this->recording = false;
    }

    /**
     * Record a telemetry entry.
     *
     * @param Entry $entry
     * @return void
     */
    public function record(Entry $entry): void
    {
        if (!$this->recording) {
            return;
        }

        // Apply filters
        foreach ($this->filters as $filter) {
            if ($filter($entry) === false) {
                return;
            }
        }

        $this->entries[] = $entry;

        // If running in CLI and buffer gets large, flush early
        if (count($this->entries) >= 50) {
            $this->flush();
        }
    }

    /**
     * Flush all buffered entries to storage.
     *
     * @return void
     */
    public function flush(): void
    {
        if (empty($this->entries)) {
            return;
        }

        $toStore = $this->entries;
        $this->entries = [];

        $this->storage->store($toStore);
    }

    /**
     * Get the configured storage driver.
     *
     * @return StorageContract
     */
    public function storage(): StorageContract
    {
        return $this->storage;
    }

    /**
     * Register a filter callback to accept/reject entries.
     *
     * @param callable $callback fn(Entry $entry): bool
     * @return $this
     */
    public function filter(callable $callback): static
    {
        $this->filters[] = $callback;
        return $this;
    }

    /**
     * Register an authorization callback for dashboard access.
     *
     * @param callable $callback fn(?Request $request): bool
     * @return $this
     */
    public function auth(callable $callback): static
    {
        $this->authCallbacks[] = $callback;
        return $this;
    }

    /**
     * Get aggregated counts grouped by entry type.
     *
     * @param string|null $since ISO timestamp
     * @return array<string, int>
     */
    public function counts(?string $since = null): array
    {
        return $this->storage->counts($since);
    }

    /**
     * Check if the given request is authorized to view Pulse.
     *
     * @param Request|null $request
     * @return bool
     */
    public function check(?Request $request = null): bool
    {
        if (!empty($this->authCallbacks)) {
            foreach ($this->authCallbacks as $callback) {
                if ($callback($request) === true) {
                    return true;
                }
            }
            return false;
        }

        // Default: allow in local/testing environment, or if debug mode is active
        $env = (string) config('app.env', env('APP_ENV', 'local'));
        return in_array($env, ['local', 'testing'], true) || is_debug_mode();
    }

    /**
     * Prune old records from storage.
     *
     * @param int|null $hours
     * @return int
     */
    public function prune(?int $hours = null): int
    {
        $hours ??= (int) config('pulse.storage.prune_hours', 24);
        return $this->storage->prune($hours);
    }

    /**
     * Clear all records from storage.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->entries = [];
        $this->storage->clear();
    }
}
