<?php

namespace Spark\Pulse;

use RuntimeException;
use Spark\Foundation\Application;
use Spark\Http\Request;
use Spark\Pulse\Contracts\StorageContract;
use Spark\Pulse\Storage\DatabaseStorage;
use Spark\Pulse\Storage\MemoryStorage;
use Spark\Support\Str;
use Spark\Support\Traits\Macroable;
use Spark\Utils\File;
use WeakReference;
use function count;
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

    /** @var WeakReference|null Weak reference to the current request for batch ID tracking */
    private static ?WeakReference $batchRequest = null;

    /** @var string|null Batch ID for the current request */
    private static ?string $requestBatchId = null;

    /** @var list<array{string, string}> Enclosing CLI command or queue execution and its parent batch. */
    private array $executions = [];

    /** @var bool Whether this process is allowed to record */
    protected bool $recording = true;

    /** @var Entry[] Ingestion buffer waiting to be flushed */
    protected array $entries = [];

    private bool $flushScheduled = false;

    private string $pauseFile;

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
        $this->pauseFile = storage_dir('framework/pulse.paused');

        $driver = config('pulse.storage.driver', 'database');
        $this->storage = $storage ?? match ($driver) {
            'memory' => new MemoryStorage(),
            default => new DatabaseStorage(),
        };
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
        $app = Application::$app;
        if ($app && $app->isHandlingRequest()) {
            $request = $app->get(Request::class);
            if (self::$batchRequest && self::$batchRequest->get() === $request && self::$requestBatchId !== null) {
                return self::$requestBatchId;
            }
            return self::newBatchId();
        }

        return self::$batchId ??= bin2hex(random_bytes(16));
    }

    /**
     * Reset and generate a new batch ID.
     *
     * @return string
     */
    public static function newBatchId(): string
    {
        $batchId = bin2hex(random_bytes(16));
        $app = Application::$app;
        if ($app && $app->isHandlingRequest()) {
            self::$batchRequest = WeakReference::create($app->get(Request::class));
            return self::$requestBatchId = $batchId;
        }

        return self::$batchId = $batchId;
    }

    /** Synchronous work inside a request stays in its HTTP trace. */
    public function beginExecution(string $scope, bool $ifMissing = false): void
    {
        if (Application::$app?->isHandlingRequest()) {
            return;
        }

        $current = end($this->executions);
        if ($ifMissing && $current && $current[0] === $scope) {
            return;
        }

        $this->executions[] = [$scope, self::getBatchId()];
        self::newBatchId();
    }

    public function endExecution(string $scope): void
    {
        $current = end($this->executions);
        if (!Application::$app?->isHandlingRequest() && $current && $current[0] === $scope) {
            array_pop($this->executions);
            self::$batchId = $current[1];
        }
    }

    /**
     * Determine if Pulse is actively recording.
     *
     * @return bool
     */
    public function isRecording(): bool
    {
        clearstatcache(true, $this->pauseFile);
        return $this->recording && !is_file($this->pauseFile);
    }

    /** Persist the dashboard switch across processes sharing application storage. */
    public function toggleRecording(): bool
    {
        $directory = dirname($this->pauseFile);
        if (!File::ensureDirectoryExists($directory) || !($lock = fopen("$directory/recording.lock", 'c'))) {
            throw new RuntimeException('Pulse recording state is not writable.');
        }
        try {
            if (!flock($lock, LOCK_EX)) {
                throw new RuntimeException('Could not lock Pulse recording state.');
            }
            clearstatcache(true, $this->pauseFile);
            $resume = is_file($this->pauseFile);
            if (!($resume ? unlink($this->pauseFile) : touch($this->pauseFile))) {
                throw new RuntimeException('Could not change Pulse recording state.');
            }
            return $this->isRecording();
        } finally {
            fclose($lock);
        }
    }

    public function shouldRecord(): bool
    {
        $app = Application::$app;
        return $this->isRecording()
            && (!$app?->isHandlingRequest() || !$this->ignoresRequest($app->get(Request::class)));
    }

    public function ignoresRequest(Request $request): bool
    {
        $path = trim($request->getPath(), '/');
        $dashboard = trim((string) config('pulse.path', 'pulse'), '/');

        return $path === $dashboard
            || ($dashboard !== '' && str_starts_with($path, $dashboard . '/'))
            || ($dashboard === '' && str_starts_with($path, 'api/'))
            || Str::is((array) config('pulse.ignore_paths', []), $path, true);
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
        if (!$this->shouldRecord()) {
            return;
        }

        // Apply filters
        foreach ($this->filters as $filter) {
            if ($filter($entry) === false) {
                return;
            }
        }

        if (!$this->entries && !$this->flushScheduled && ($app = Application::$app) !== null) {
            $this->flushScheduled = true;
            $app->defer(function () {
                $this->flushScheduled = false;
                $this->flush();
            });
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

        $env = (string) config(
            'app.env',
            env('APP_ENV', is_debug_mode() ? 'local' : 'production')
        );
        return in_array($env, ['local', 'testing'], true);
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
        if ($hours < 1) {
            throw new \InvalidArgumentException('Prune hours must be a positive integer.');
        }
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
