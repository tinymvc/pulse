<?php

namespace Spark\Pulse\Http\Controllers;

use Spark\Http\Request;
use Spark\Http\Response;
use Spark\Pulse\EntryType;
use Spark\Pulse\Pulse;
use Spark\Pulse\Recorders\ServerPerformanceRecorder;

/**
 * Pulse Dashboard Controller.
 *
 * @package Spark\Pulse\Http\Controllers
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class DashboardController
{
    /**
     * Render the main Pulse dashboard view.
     */
    public function index(Request $request): Response
    {
        $pulse = Pulse::instance();

        // Collect high-level counts for all 14 modules
        $counts = [];
        foreach (EntryType::all() as $type) {
            $counts[$type] = $pulse->storage()->count($type);
        }

        $server = ServerPerformanceRecorder::sample();

        return view('pulse::dashboard', [
            'counts' => $counts,
            'server' => $server,
            'pulse' => $pulse,
            'activeTab' => (string) $request->query('tab', 'dashboard'),
            'path' => '/' . ltrim((string) config('pulse.path', 'pulse'), '/'),
        ]);
    }
}
