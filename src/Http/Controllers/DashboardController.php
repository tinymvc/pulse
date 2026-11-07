<?php

namespace Spark\Pulse\Http\Controllers;

use Spark\Facades\Blade;
use Spark\Http\Request;
use Spark\Http\Response;
use Spark\Pulse\EntryType;
use Spark\Pulse\Pulse;
use Spark\Pulse\Recorders\ServerPerformanceRecorder;

class DashboardController
{
    public function index(Request $request): Response
    {
        // Spark creates a fresh Blade instance when handling another request.
        $published = views_dir('vendor/pulse');

        Blade::usePath(
            is_file("$published/dashboard.blade.php") ? $published : dirname(__DIR__, 3) . '/resources/views',
            'pulse'
        );

        $query = array_filter($request->query->all(), 'is_string');
        $type = EntryType::normalize($query['tab'] ?? '');
        $tab = EntryType::isValid($type) ? $type : 'dashboard';
        $overview = $tab === 'dashboard';
        $search = $query['search'] ?? '';
        $options = [
            'limit' => $overview ? 7 : 30,
            'search' => $overview ? '' : $search,
            'before' => $query['before'] ?? null,
            'before_id' => $query['before_id'] ?? null,
        ];

        $pulse = Pulse::instance();
        $storage = $pulse->storage();
        $entry = null;
        $trace = [];
        if (isset($query['entry'])) {
            $entry = $storage->find($query['entry']);
            if ($entry === null) {
                return Response::error(Response::STATUS_NOT_FOUND, 'This entry is no longer available.');
            }
            $trace = $storage->getByBatch($entry['batch_id']);
        }

        $page = $entry ? null : $storage->get($overview ? EntryType::REQUEST : $tab, $options);
        $data = [
            'tab' => $tab,
            'overview' => $overview,
            'groups' => EntryType::groups(),
            'counts' => $pulse->counts(),
            'server' => $overview && !$entry
                ? ServerPerformanceRecorder::sample()
                : ['runtime' => ServerPerformanceRecorder::sampleRuntime()],
            'recording' => $pulse->isRecording(),
            'entry' => $entry,
            'trace' => $trace,
            'languageLoads' => ($entry['type'] ?? null) === EntryType::REQUEST ? $this->languageLoads($trace) : [],
            'page' => $page,
            'search' => $search,
            'path' => route_url('pulse.dashboard'),
            'filters' => array_intersect_key($query, array_flip(['tab', 'before', 'before_id', 'search'])),
        ];

        return view($request->header('X-Pulse-Refresh') === '1' ? 'pulse::workspace' : 'pulse::dashboard', $data);
    }

    /** Summarize the files in this execution using its already-loaded trace. */
    private function languageLoads(array $trace): array
    {
        $count = 0;
        $largest = null;
        foreach ($trace as $step) {
            if ($step['type'] !== EntryType::LANGUAGE_FILE) {
                continue;
            }
            $count++;
            if (($step['content']['allocated_change_bytes'] ?? 0) > ($largest['content']['allocated_change_bytes'] ?? 0)) {
                $largest = $step;
            }
        }
        return ['count' => $count, 'largest' => $largest];
    }
}
