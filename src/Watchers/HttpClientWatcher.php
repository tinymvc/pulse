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
        Telemetry::listen('http.request', function (
            string|array $url,
            array $req = [],
            array $resp = [],
            float $duration = 0.0
        ) {
            if (is_array($url)) {
                $req = $url['payload'] ?? [];
                $resp = $url['response'] ?? [];
                $duration = (float) ($url['duration_ms'] ?? 0);
                $url = (string) ($url['url'] ?? '');
            }
            $method = strtoupper((string) ($req['method'] ?? 'GET'));
            $status = (int) ($resp['status'] ?? 0);

            $parsedUrl = parse_url($url);
            $domain = $parsedUrl['host'] ?? '';
            $authority = $domain === '' ? '' : ($parsedUrl['scheme'] ?? 'https') . '://' . $domain;
            if (isset($parsedUrl['port'])) {
                $authority .= ':' . $parsedUrl['port'];
            }
            $url = $authority . ($parsedUrl['path'] ?? '/');
            $req['url'] = $url;
            $req['headers'] = $this->headers($req['headers'] ?? []);
            if (isset($req['body']) && is_string($req['body'])) {
                $body = json_decode($req['body'], true);
                $req['body'] = is_array($body) ? $body : '[raw body omitted]';
            }

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
                'payload' => $req,
                'response_status' => $status,
                'response_headers' => $this->headers($resp['headers'] ?? []),
            ])
            ->duration($duration)
            ->tags($tags);

            $this->record($entry);
        });
    }
}
