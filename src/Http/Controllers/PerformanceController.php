<?php

namespace Spark\Pulse\Http\Controllers;

use Spark\Http\Request;
use Spark\Http\Response;
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

        return json([
            'server' => ServerPerformanceRecorder::sample(),
            'counts' => $pulse->counts(),
            'recording' => $pulse->isRecording(),
        ]);
    }
}
