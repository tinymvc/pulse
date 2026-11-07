<?php

namespace Spark\Pulse\Watchers;

use Closure;
use Spark\Exceptions\Http\ResponseException;
use Spark\Foundation\Application;
use Spark\Http\Request;
use Spark\Http\Response;
use Spark\Pulse\Entry;
use Spark\Pulse\EntryType;
use Spark\Telemetry;
use Throwable;
use WeakMap;
use WeakReference;
use function array_slice;

/** Records framework-reported exceptions and route failures rendered by application middleware. */
class ExceptionWatcher extends Watcher
{
    /** @var WeakMap<Throwable, WeakReference<Request>> */
    private WeakMap $reported;

    public function __construct()
    {
        parent::__construct('exceptions');
    }

    protected function boot(): void
    {
        $this->reported = new WeakMap();
        Telemetry::listen('exception.occurred', function (array $payload): void {
            $error = $payload['exception'] ?? null;
            if (!$error instanceof Throwable) {
                return;
            }

            $request = $payload['request'] ?? null;
            // The terminal tracer can report the same throwable again after HTTP dispatch unwinds.
            $terminalReport = ($payload['type'] ?? null) === 'Exception' && Application::$app->resolved(Request::class);
            if ($request === null && (Application::$app->isHandlingRequest() || $terminalReport)) {
                $request = Application::$app->get(Request::class);
            }
            $this->recordException($error, (bool) ($payload['handled'] ?? false), $request);
        });

        $this->observeMiddlewareFailures();
    }

    private function observeMiddlewareFailures(): void
    {
        /** @var WeakMap<Response, Throwable> $failures */
        $failures = new WeakMap();
        $observe = static function (Request $request, Closure $next) use ($failures): mixed {
            try {
                return $next($request);
            } catch (Throwable $error) {
                if (!$error instanceof ResponseException) {
                    Application::$app->prepareResponseUsing(static function (Response $response) use ($error, $failures): void {
                        $failures[$response] = $error;
                    });
                }
                throw $error;
            }
        };

        // Application globals wrap this observer and may turn the original throwable into a response.
        Application::$app->withMiddleware(
            register: ['pulse.exceptions' => $observe],
            queue: 'pulse.exceptions'
        );

        Telemetry::listen('request.handled', function (array $payload) use ($failures): void {
            $response = $payload['response'] ?? null;
            if ($response instanceof Response && isset($failures[$response])) {
                $error = $failures[$response];
                unset($failures[$response]);
                if ($response->getStatusCode() >= 500) {
                    $this->recordException($error, true, $payload['request']);
                }
            }
        });
    }

    private function recordException(Throwable $error, bool $handled, ?Request $request): void
    {
        if ($request !== null) {
            if (($this->reported[$error] ?? null)?->get() === $request) {
                return;
            }
            $this->reported[$error] = WeakReference::create($request);
        }

        $class = $error::class;
        if (!$this->pulse->shouldRecord() || $this->isExcluded($class)) {
            return;
        }

        $trace = [];
        foreach (array_slice($error->getTrace(), 0, 30) as $frame) {
            $trace[] = [
                'file' => $frame['file'] ?? '[internal]',
                'line' => $frame['line'] ?? 0,
                'function' => ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? ''),
            ];
        }

        $entry = Entry::make(EntryType::EXCEPTION, [
            'class' => $class,
            'message' => $error->getMessage(),
            'file' => $error->getFile(),
            'line' => $error->getLine(),
            'code' => $error->getCode(),
            'trace' => $trace,
            'handled' => $handled,
        ])->familyHash(md5("$class:" . $error->getFile() . ':' . $error->getLine()))
            ->tags([$class, 'error']);

        $this->record($entry);
    }
}
