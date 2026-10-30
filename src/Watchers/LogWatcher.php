<?php

namespace Spark\Pulse\Watchers;

use Spark\Pulse\Entry;
use Spark\Pulse\EntryType;
use Spark\Telemetry;

/**
 * Application Log Watcher.
 *
 * Records PSR-3 log entries, severity levels, and context metadata.
 *
 * @package Spark\Pulse\Watchers
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class LogWatcher extends Watcher
{
    public function __construct()
    {
        parent::__construct('logs');
    }

    protected function boot(): void
    {
        Telemetry::listen('log.logged', function (array $payload) {
            $level = strtolower((string) ($payload['level'] ?? 'info'));
            $message = (string) ($payload['message'] ?? '');
            $context = (array) ($payload['context'] ?? []);

            $tags = [$level];
            if (in_array($level, ['emergency', 'alert', 'critical', 'error'], true)) {
                $tags[] = 'error';
            }

            $entry = Entry::make(EntryType::LOG, [
                'level' => $level,
                'message' => $message,
                'context' => $this->mask($context),
            ])
            ->tags($tags);

            $this->record($entry);
        });
    }
}
