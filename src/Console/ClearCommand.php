<?php

namespace Spark\Pulse\Console;

use Spark\Console\Prompt;
use Spark\Pulse\Pulse;

/**
 * Clear Spark Pulse Telemetry Entries.
 *
 * @package Spark\Pulse\Console
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class ClearCommand
{
    /**
     * Execute the command.
     *
     * @param array $args
     * @return int
     */
    public function handle(array $args = []): int
    {
        Pulse::instance()->clear();
        Prompt::message('All Spark Pulse recorded entries have been cleared.', 'success');

        return 0;
    }
}
