<?php

namespace Spark\Pulse\Console;

use Spark\Console\Prompt;
use Spark\Pulse\Pulse;

/**
 * Prune Old Spark Pulse Telemetry Entries.
 *
 * @package Spark\Pulse\Console
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class PruneCommand
{
    /**
     * Execute the command.
     *
     * @param array $args
     * @return int
     */
    public function handle(array $args = []): int
    {
        $hours = (int) ($args['hours'] ?? config('pulse.storage.prune_hours', 24));
        if ($hours <= 0) {
            $hours = 24;
        }

        $count = Pulse::instance()->prune($hours);
        Prompt::message("Pruned {$count} Pulse entries older than {$hours} hours.", 'success');

        return 0;
    }
}
