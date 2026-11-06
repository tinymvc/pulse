<?php

namespace Spark\Pulse\Watchers;

use Spark\Pulse\Entry;
use Spark\Pulse\EntryType;
use Spark\Telemetry;

/**
 * Blade View Watcher.
 *
 * Records rendered Blade templates, compilation paths, render durations, and variable keys.
 *
 * @package Spark\Pulse\Watchers
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class ViewWatcher extends Watcher
{
    public function __construct()
    {
        parent::__construct('views');
    }

    protected function boot(): void
    {
        Telemetry::listen('bladeTemplateRendered', function (array $payload) {
            $path = (string) ($payload['path'] ?? '');

            // Don't record internal pulse dashboard view renderings to avoid noise
            if (str_contains($path, 'pulse') || str_contains($path, 'Spark_Pulse')) {
                return;
            }

            $time = (float) ($payload['time'] ?? 0.0);
            $template = $payload['template'] ?? basename($path, '.blade.php');

            $entry = Entry::make(EntryType::VIEW, [
                'template' => $template,
                'path' => $path,
                'duration_ms' => $time,
                'data_keys' => (array) ($payload['data_keys'] ?? []),
            ])
                ->duration($time)
                ->tags([(string) $template]);

            $this->record($entry);
        });
    }
}
