<?php

namespace Spark\Pulse\Watchers;

use Spark\Pulse\Entry;
use Spark\Pulse\EntryType;
use Spark\Telemetry;

/**
 * Console Command Watcher.
 *
 * Records CLI console command executions, arguments, exit codes, and durations.
 *
 * @package Spark\Pulse\Watchers
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class CommandWatcher extends Watcher
{
    public function __construct()
    {
        parent::__construct('commands');
    }

    protected function boot(): void
    {
        Telemetry::listen('command.finished', function (array $payload) {
            $name = (string) ($payload['name'] ?? '');
            if ($name === '') {
                return;
            }

            // Check if command is ignored
            $ignored = (array) config('pulse.ignore_commands', ['pulse:*', 'queue:work']);
            foreach ($ignored as $pattern) {
                $patternRegex = '#^' . str_replace('\*', '.*', preg_quote($pattern, '#')) . '#i';
                if (preg_match($patternRegex, $name)) {
                    return;
                }
            }

            $duration = (float) ($payload['duration_ms'] ?? 0.0);
            $exitCode = (int) ($payload['exit_code'] ?? 0);

            $tags = [$name, $exitCode === 0 ? 'success' : 'failed'];

            $entry = Entry::make(EntryType::COMMAND, [
                'name' => $name,
                'arguments' => $payload['args'] ?? [],
                'exit_code' => $exitCode,
                'duration_ms' => $duration,
            ])
            ->duration($duration)
            ->tags($tags);

            $this->record($entry);
        });
    }
}
