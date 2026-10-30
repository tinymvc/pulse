<?php

namespace Spark\Pulse\Http\Controllers;

use Spark\Http\Request;
use Spark\Http\Response;
use Spark\Pulse\Pulse;

/**
 * Pulse Entry API Controller.
 *
 * Serves paginated telemetry entries, filters, and waterfall traces.
 *
 * @package Spark\Pulse\Http\Controllers
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class EntryController
{
    /**
     * Get paginated entries for a specific type.
     */
    public function index(Request $request): Response
    {
        $type = (string) $request->query('type', 'request');

        // Normalize plural forms if supplied
        $pluralMap = [
            'requests' => 'request',
            'commands' => 'command',
            'jobs' => 'job',
            'events' => 'event',
            'exceptions' => 'exception',
            'gates' => 'gate',
            'logs' => 'log',
            'queries' => 'query',
            'views' => 'view',
            'models' => 'model',
        ];
        if (isset($pluralMap[$type])) {
            $type = $pluralMap[$type];
        }
        $options = [
            'limit' => (int) $request->query('limit', 50),
            'before' => $request->query('before'),
            'search' => $request->query('search'),
            'family_hash' => $request->query('family_hash'),
            'batch_id' => $request->query('batch_id'),
            'slow_only' => (bool) $request->query('slow_only', false),
        ];

        $data = Pulse::instance()->storage()->get($type, $options);

        return json($data);
    }

    /**
     * Get a single entry by ID.
     */
    public function show(Request $request, string $id): Response
    {
        $entry = Pulse::instance()->storage()->find($id);
        if (!$entry) {
            return json(['error' => 'Entry not found'], 404);
        }

        return json($entry);
    }

    /**
     * Get all entries sharing a batch trace ID for the waterfall view.
     */
    public function batch(Request $request, string $batchId): Response
    {
        $entries = Pulse::instance()->storage()->getByBatch($batchId);

        return json([
            'batch_id' => $batchId,
            'count' => count($entries),
            'entries' => $entries,
        ]);
    }
}
