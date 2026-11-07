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
        Telemetry::listen('bladeTemplateRendered', function (string|array $path, float $time = 0, int $memory = 0, array $keys = []) {
            if (is_array($path)) {
                $time = (float) ($path['time'] ?? 0);
                $keys = $path['data_keys'] ?? [];
                $template = $path['template'] ?? null;
                $path = (string) ($path['path'] ?? '');
            }
            $template ??= preg_replace('/_[a-f0-9]{32}\\.php$/', '', basename($path));
            if (!$this->pulse->shouldRecord()) {
                return;
            }

            $entry = Entry::make(EntryType::VIEW, [
                'template' => $template,
                'path' => $path,
                'duration_ms' => $time,
                'data_keys' => $keys,
            ])
                ->duration($time)
                ->tags([(string) $template]);

            $this->record($entry);
        });
    }
}
