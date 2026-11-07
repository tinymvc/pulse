<?php

namespace Spark\Pulse\Watchers;

use Spark\Pulse\Entry;
use Spark\Pulse\EntryType;
use Spark\Telemetry;

class LanguageFileWatcher extends Watcher
{
    public function __construct()
    {
        parent::__construct('language_files');
    }

    protected function boot(): void
    {
        Telemetry::listen('translator.loadedLanguageFile', function (string $file, float $duration, int $memoryBefore): void {
            // Match TinyCore's pre-include sample before building the Pulse entry.
            $memoryAfter = memory_get_usage(true);
            if (!$this->pulse->shouldRecord()) {
                return;
            }

            $root = root_dir() . DIRECTORY_SEPARATOR;
            $path = str_starts_with($file, $root) ? substr($file, strlen($root)) : $file;
            $path = str_replace(DIRECTORY_SEPARATOR, '/', $path);
            if ($this->isExcluded($path)) {
                return;
            }

            $entry = Entry::make(EntryType::LANGUAGE_FILE, [
                'path' => $path,
                'duration_ms' => $duration,
                'allocated_before_bytes' => $memoryBefore,
                'allocated_after_bytes' => $memoryAfter,
                'allocated_change_bytes' => $memoryAfter - $memoryBefore,
            ])->duration($duration);

            $this->record($entry);
        }, priority: PHP_INT_MAX);
    }
}
