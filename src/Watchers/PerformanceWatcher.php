<?php

namespace Spark\Pulse\Watchers;

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
        // Sample performance when explicitly requested or at sample rate
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
