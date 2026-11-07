<?php

namespace Spark\Pulse\Http\Controllers;

use Spark\Http\Request;
use Spark\Http\Response;
use Spark\Pulse\EntryType;
use Spark\Pulse\Pulse;
use function is_string;

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
        $type = $request->query('type', 'request');
        if (!is_string($type) || !EntryType::isValid($type)) {
            return json(['error' => 'Unknown entry type.'], Response::STATUS_UNPROCESSABLE_ENTITY);
        }
        $type = EntryType::normalize($type);

        $options = [
            'limit' => (int) $request->query('limit', 50),
            'before' => $request->query('before'),
            'before_id' => $request->query('before_id'),
            'search' => $request->query('search'),
            'family_hash' => $request->query('family_hash'),
            'batch_id' => $request->query('batch_id'),
            'slow_only' => filter_var($request->query('slow_only'), FILTER_VALIDATE_BOOLEAN),
        ];

        foreach (['before', 'before_id', 'search', 'family_hash', 'batch_id'] as $key) {
            if (isset($options[$key]) && !is_string($options[$key])) {
                return json(['error' => "Invalid $key filter."], Response::STATUS_UNPROCESSABLE_ENTITY);
            }
        }

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
            return json(['error' => 'Entry not found'], Response::STATUS_NOT_FOUND);
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
