<?php

namespace Spark\Pulse\Facades;

use Spark\Facades\Facade;
use Spark\Pulse\Pulse as PulseCore;

/**
 * Pulse Facade for Spark.
 *
 * @method static void startRecording()
 * @method static void stopRecording()
 * @method static bool isRecording()
 * @method static string getBatchId()
 * @method static string newBatchId()
 * @method static void record(\Spark\Pulse\Entry $entry)
 * @method static void flush()
 * @method static \Spark\Pulse\Contracts\StorageContract storage()
 * @method static \Spark\Pulse\Pulse filter(callable $callback)
 * @method static \Spark\Pulse\Pulse auth(callable $callback)
 * @method static bool check(?\Spark\Http\Request $request = null)
 * @method static int prune(?int $hours = null)
 * @method static void clear()
 *
 * @package Spark\Pulse\Facades
 */
class Pulse extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return PulseCore::class;
    }
}
