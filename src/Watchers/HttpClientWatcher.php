<?php

namespace Spark\Pulse\Watchers;

use Spark\Pulse\Entry;
use Spark\Pulse\EntryType;
use Spark\Telemetry;

/**
 * Outbound HTTP Client Watcher.
 *
 * Records external HTTP requests initiated through Spark\Http\Client\Http.
 *
 * @package Spark\Pulse\Watchers
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class HttpClientWatcher extends Watcher
{
    public function __construct()
    {
        parent::__construct('http_client');
    }

    protected function boot(): void
    {
        Telemetry::listen('http.request', function (array $payload) {
            $url = (string) ($payload['url'] ?? '');
            $duration = (float) ($payload['duration_ms'] ?? 0.0);
            $req = (array) ($payload['payload'] ?? []);
            $resp = (array) ($payload['response'] ?? []);

            $method = strtoupper((string) ($req['method'] ?? 'GET'));
            $status = (int) ($resp['status'] ?? 0);

            $parsedUrl = parse_url($url);
            $domain = $parsedUrl['host'] ?? $url;

            $slowThreshold = (float) config('pulse.slow_thresholds.http_client', 1000);
            $tags = [$method, $domain];
            if ($duration >= $slowThreshold) {
                $tags[] = 'slow';
            }
            if ($status >= 400 || $status === 0) {
                $tags[] = 'error';
            }

            $entry = Entry::make(EntryType::HTTP_CLIENT, [
                'method' => $method,
                'url' => $url,
                'domain' => $domain,
                'status' => $status,
                'duration_ms' => $duration,
                'payload' => $this->mask($req),
                'response_status' => $status,
                'response_headers' => $resp['headers'] ?? [],
            ])
            ->duration($duration)
            ->tags($tags);

            $this->record($entry);
        });
    }
}
