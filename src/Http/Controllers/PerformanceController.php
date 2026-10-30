<?php

namespace Spark\Pulse\Http\Controllers;

use Spark\Http\Request;
use Spark\Http\Response;
use Spark\Pulse\EntryType;
use Spark\Pulse\Pulse;
use Spark\Pulse\Recorders\ServerPerformanceRecorder;

/**
 * Pulse Real-time Performance & Metrics Controller.
 *
 * @package Spark\Pulse\Http\Controllers
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class PerformanceController
{
    /**
     * Return real-time performance sample and module counts.
     */
    public function index(Request $request): Response
    {
        $pulse = Pulse::instance();

        $counts = [];
        foreach (EntryType::all() as $type) {
            $counts[$type] = $pulse->storage()->count($type);
        }

        $sample = ServerPerformanceRecorder::sample();

        return json([
            'server' => $sample,
            'counts' => $counts,
            'recording' => $pulse->isRecording(),
        ]);
    }
}
