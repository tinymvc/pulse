<?php

namespace Spark\Pulse\Console;

use Spark\Console\Prompt;
use Spark\Pulse\EntryType;
use Spark\Pulse\Pulse;
use Spark\Pulse\Recorders\ServerPerformanceRecorder;
use function sprintf;

/**
 * Spark Pulse Status & Diagnostic Check Command.
 *
 * @package Spark\Pulse\Console
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class CheckCommand
{
    /**
     * Execute the command.
     *
     * @param array $args
     * @return int
     */
    public function handle(array $args = []): int
    {
        Prompt::section('Spark Pulse Health & Diagnostics');

        $pulse = Pulse::instance();
        $enabled = $pulse->isRecording();

        Prompt::message('Status: ' . ($enabled ? 'ACTIVE (Recording)' : 'PAUSED (Not recording)'), $enabled ? 'success' : 'warning');
        Prompt::message('Storage Driver: ' . config('pulse.storage.driver', 'database'), 'info');
        Prompt::message('Dashboard Path: /' . ltrim(config('pulse.path', 'pulse'), '/'), 'info');

        Prompt::newline();
        Prompt::section('Recorded Entries by Module');

        foreach ($pulse->counts() as $type => $count) {
            Prompt::message(sprintf('  %-15s : %d entries', ucfirst($type), $count));
        }

        Prompt::newline();
        Prompt::section('Server Quick Metrics');
        $sample = ServerPerformanceRecorder::sample();

        Prompt::message('  CPU Load       : ' . ($sample['cpu']['usage_percent'] ?? 0) . '% (' . ($sample['cpu']['cores'] ?? 1) . ' cores)');
        Prompt::message('  App Memory     : ' . ($sample['memory']['app_formatted'] ?? 'N/A') . ' (Peak: ' . ($sample['memory']['app_peak_formatted'] ?? 'N/A') . ')');
        Prompt::message('  Disk Usage     : ' . ($sample['disk']['used_formatted'] ?? 'N/A') . ' of ' . ($sample['disk']['total_formatted'] ?? 'N/A') . ' (' . ($sample['disk']['usage_percent'] ?? 0) . '%)');
        Prompt::newline();

        return 0;
    }
}
