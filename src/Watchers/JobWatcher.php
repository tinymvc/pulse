<?php

namespace Spark\Pulse\Watchers;

use Spark\Pulse\Entry;
use Spark\Pulse\EntryType;
use Spark\Queue\Contracts\JobContract;
use Spark\Telemetry;
use function get_class;
use function is_object;

/**
 * Queue Job Watcher.
 *
 * Records queued jobs, dispatching, queue workers execution, durations, and failures.
 *
 * @package Spark\Pulse\Watchers
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class JobWatcher extends Watcher
{
    public function __construct()
    {
        parent::__construct('jobs');
    }

    protected function boot(): void
    {
        // Pushed
        Telemetry::listen('job.pushed', function (array $payload) {
            $job = $payload['job'] ?? null;
            $queue = (string) ($payload['queue'] ?? 'default');

            $jobName = $job instanceof JobContract ? $job->getDisplayName() : (is_object($job) ? get_class($job) : 'Job');

            $entry = Entry::make(EntryType::JOB, [
                'status' => 'queued',
                'name' => $jobName,
                'queue' => $queue,
                'payload' => $job instanceof JobContract ? $job->getParameters() : [],
            ])
                ->tags([$jobName, $queue, 'queued']);

            $this->record($entry);
        });

        // Processed
        Telemetry::listen('job.processed', function (array $payload) {
            $job = $payload['job'] ?? null;
            $queue = (string) ($payload['queue'] ?? 'default');
            $duration = (float) ($payload['duration_ms'] ?? 0.0);
            $jobName = $job instanceof JobContract ? $job->getDisplayName() : (is_object($job) ? get_class($job) : 'Job');

            $slowThreshold = (float) config('pulse.slow_thresholds.job', 1000);
            $tags = [$jobName, $queue, 'processed'];
            if ($duration >= $slowThreshold) {
                $tags[] = 'slow';
            }

            $entry = Entry::make(EntryType::JOB, [
                'status' => 'processed',
                'id' => $payload['id'] ?? null,
                'name' => $jobName,
                'queue' => $queue,
                'duration_ms' => $duration,
            ])
                ->duration($duration)
                ->tags($tags);

            $this->record($entry);
        });

        // Failed
        Telemetry::listen('job.failed', function (array $payload) {
            $job = $payload['job'] ?? null;
            $queue = (string) ($payload['queue'] ?? 'default');
            $duration = (float) ($payload['duration_ms'] ?? 0.0);
            $exception = $payload['exception'] ?? null;
            $jobName = $job instanceof JobContract ? $job->getDisplayName() : (is_object($job) ? get_class($job) : 'Job');

            $entry = Entry::make(EntryType::JOB, [
                'status' => 'failed',
                'id' => $payload['id'] ?? null,
                'name' => $jobName,
                'queue' => $queue,
                'duration_ms' => $duration,
                'attempts' => $payload['attempts'] ?? 1,
                'exception' => $exception instanceof \Throwable ? [
                    'class' => get_class($exception),
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                ] : null,
            ])
                ->duration($duration)
                ->tags([$jobName, $queue, 'failed']);

            $this->record($entry);
        });
    }
}
