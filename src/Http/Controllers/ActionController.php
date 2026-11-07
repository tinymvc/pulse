<?php

namespace Spark\Pulse\Http\Controllers;

use Spark\Http\Request;
use Spark\Http\Response;
use Spark\Pulse\Pulse;

/**
 * Pulse Actions Controller (Clear, Prune, Toggle).
 *
 * @package Spark\Pulse\Http\Controllers
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class ActionController
{
    /**
     * Clear all recorded entries.
     */
    public function clear(Request $request): Response
    {
        Pulse::instance()->clear();

        return json(['status' => 'success', 'success' => true, 'message' => 'Entries cleared']);
    }

    /**
     * Prune old records.
     */
    public function prune(Request $request): Response
    {
        $hours = filter_var($request->input('hours', config('pulse.storage.prune_hours', 24)), FILTER_VALIDATE_INT);
        if ($hours === false || $hours < 1) {
            return json(['error' => 'Hours must be a positive integer.'], Response::STATUS_UNPROCESSABLE_ENTITY);
        }
        $count = Pulse::instance()->prune($hours);

        return json(['status' => 'success', 'pruned' => $count]);
    }

    /**
     * Toggle recording state.
     */
    public function toggle(Request $request): Response
    {
        return json(['status' => 'success', 'recording' => Pulse::instance()->toggleRecording()]);
    }
}
