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
        $hours = (int) $request->input('hours', config('pulse.storage.prune_hours', 24));
        $count = Pulse::instance()->prune($hours);

        return json(['status' => 'success', 'pruned' => $count]);
    }

    /**
     * Toggle recording state.
     */
    public function toggle(Request $request): Response
    {
        $pulse = Pulse::instance();
        if ($pulse->isRecording()) {
            $pulse->stopRecording();
        } else {
            $pulse->startRecording();
        }

        return json(['status' => 'success', 'recording' => $pulse->isRecording()]);
    }
}
