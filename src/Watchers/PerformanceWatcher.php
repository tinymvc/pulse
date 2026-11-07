<?php

namespace Spark\Pulse\Watchers;

use Spark\Http\Request;
use Spark\Telemetry;
use Spark\Pulse\Entry;
use Spark\Pulse\EntryType;
use Spark\Pulse\Recorders\ServerPerformanceRecorder;

/**
 * Server & Application Performance Watcher.
 *
 * Samples CPU, Memory, Disk, and Runtime metrics for health monitoring.
 *
 * @package Spark\Pulse\Watchers
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class PerformanceWatcher extends Watcher
{
    public function __construct()
    {
        parent::__construct('performance');
    }

    protected function boot(): void
    {
        Telemetry::listen('request.handled', function (array $payload) {
            $rate = (int) config('pulse.watchers.performance.sample_rate', 1);
            $request = $payload['request'] ?? null;
            if (!$request instanceof Request || $rate <= 0 || !$this->pulse->isRecording() || $this->pulse->ignoresRequest($request)) {
                return;
            }
            if (!$this->isPathExcluded($request->getPath()) && random_int(1, 100) <= $rate) {
                $this->captureSnapshot();
            }
        });
    }

    /**
     * Record a performance snapshot to storage.
     *
     * @return Entry
     */
    public function captureSnapshot(): Entry
    {
        $sample = ServerPerformanceRecorder::sample();

        $entry = Entry::make(EntryType::PERFORMANCE, $sample)
            ->tags(['server', 'metrics']);

        $this->record($entry);

        return $entry;
    }
}
