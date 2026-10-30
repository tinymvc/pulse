<?php

namespace Spark\Pulse\Contracts;

use Spark\Pulse\Pulse;

/**
 * Watcher Contract for Spark Pulse telemetry collectors.
 *
 * @package Spark\Pulse\Contracts
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
interface WatcherContract
{
    /**
     * Register the watcher and attach required event hooks.
     *
     * @param Pulse $pulse
     * @return void
     */
    public function register(Pulse $pulse): void;

    /**
     * Determine whether the watcher is currently enabled.
     *
     * @return bool
     */
    public function isEnabled(): bool;
}
