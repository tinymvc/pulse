<?php

namespace Spark\Pulse\Watchers;

use Spark\Pulse\Entry;
use Spark\Pulse\EntryType;
use Spark\Queue\Contracts\JobContract;
use Spark\Telemetry;
use function get_class;
use function is_array;
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

            $jobName = $this->recordableName($job);
            if ($jobName === null) {
                return;
            }

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
            $duration = $payload['duration_ms'] ?? null;
            $jobName = $this->recordableName($job);
            if ($jobName === null) {
                return;
            }

            $entry = Entry::make(EntryType::JOB, [
                'status' => 'processed',
                'id' => $payload['id'] ?? null,
                'name' => $jobName,
                'queue' => $queue,
                'duration_ms' => $duration,
            ])
                ->duration($duration)
                ->tags($this->attemptTags($jobName, $queue, 'processed', $duration));

            $this->record($entry);
        });

        // Failed
        Telemetry::listen('job.failed', function (array $payload) {
            $job = $payload['job'] ?? null;
            $queue = (string) ($payload['queue'] ?? 'default');
            $duration = $payload['duration_ms'] ?? null;
            $exception = $payload['exception'] ?? null;
            $jobName = $this->recordableName($job);
            if ($jobName === null) {
                return;
            }

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
                ->tags($this->attemptTags($jobName, $queue, 'failed', $duration));

            $this->record($entry);
        });
    }

    private function attemptTags(string $name, string $queue, string $status, ?float $duration): array
    {
        $tags = [$name, $queue, $status];
        if ($duration !== null && $duration >= (float) config('pulse.slow_thresholds.job', 1000)) {
            $tags[] = 'slow';
        }
        return $tags;
    }

    private function recordableName(mixed $job): ?string
    {
        $class = is_object($job) ? get_class($job) : 'Job';
        if (!$job instanceof JobContract) {
            return $this->isExcluded($class) ? null : $class;
        }

        $name = $job->getDisplayName();
        $callback = $job->getCallback();
        $handler = is_array($callback) ? ($callback[0] ?? '') : $callback;
        $handlerClass = is_object($handler) ? $handler::class : explode('::', (string) $handler, 2)[0];

        return $this->isExcluded($name, $handlerClass, $class) ? null : $name;
    }
}
