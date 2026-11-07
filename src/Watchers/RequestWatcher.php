<?php

namespace Spark\Pulse\Watchers;

use Spark\Http\Request;
use Spark\Http\Response;
use Spark\Pulse\Entry;
use Spark\Pulse\EntryType;
use Spark\Telemetry;
use function in_array;

/**
 * Request Watcher.
 *
 * Records incoming HTTP requests, controllers, response statuses,
 * execution durations, memory usage, and user authentication.
 *
 * @package Spark\Pulse\Watchers
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class RequestWatcher extends Watcher
{
    public function __construct()
    {
        parent::__construct('requests');
    }

    protected function boot(): void
    {
        Telemetry::listen('request.handled', function (array $payload) {
            /** @var Request $request */
            $request = $payload['request'] ?? null;
            /** @var Response $response */
            $response = $payload['response'] ?? null;

            if (!$request instanceof Request || !$response instanceof Response) {
                return;
            }

            if ($this->pulse->ignoresRequest($request) || $this->isPathExcluded($request->getPath())) {
                return;
            }

            $duration = $payload['duration_ms'] ?? null;
            $statusCode = $response->getStatusCode();

            if (in_array($statusCode, (array) config('pulse.watchers.requests.ignore_status_codes', []))) {
                return;
            }

            $user = null;
            try {
                if (function_exists('auth') && auth()->check()) {
                    $u = auth()->user();
                    $user = [
                        'id' => method_exists($u, 'primaryValue') ? $u->primaryValue() : ($u->id ?? null),
                        'email' => $u->email ?? null,
                        'name' => $u->name ?? ($u->username ?? null),
                    ];
                }
            } catch (\Throwable) {
            }

            $slowThreshold = (float) config('pulse.slow_thresholds.request', 500);
            $isSlow = $duration !== null && $duration >= $slowThreshold;

            $tags = [$request->getMethod(), (string) $statusCode];
            if ($isSlow) {
                $tags[] = 'slow';
            }
            if ($statusCode >= 400) {
                $tags[] = 'error';
            }

            $entry = Entry::make(EntryType::REQUEST, [
                'method' => $request->getMethod(),
                'uri' => $request->getPath(),
                'status' => $statusCode,
                'duration_ms' => $duration,
                'memory_peak' => $payload['memory_peak'] ?? null,
                'ip' => $request->ip(),
                'headers' => $request->getHeaders()->all(),
                'input' => $request->input()->all(),
                'user' => $user,
                'response_status' => $statusCode,
                'response_headers' => $response->getHeaders(),
            ])
                ->duration($duration)
                ->tags($tags);

            $this->record($entry);
        });
    }
}
