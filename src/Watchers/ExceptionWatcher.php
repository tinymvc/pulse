<?php

namespace Spark\Pulse\Watchers;

use Spark\Pulse\Entry;
use Spark\Pulse\EntryType;
use Spark\Telemetry;
use Throwable;

/**
 * Exception Watcher.
 *
 * Records uncaught and handled exceptions with stack traces,
 * family grouping hashes, and execution context.
 *
 * @package Spark\Pulse\Watchers
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class ExceptionWatcher extends Watcher
{
    public function __construct()
    {
        parent::__construct('exceptions');
    }

    protected function boot(): void
    {
        Telemetry::listen('exception.occurred', function (array $payload) {
            $e = $payload['exception'] ?? null;
            if (!$e instanceof Throwable) {
                return;
            }

            $class = get_class($e);
            $file = $e->getFile();
            $line = $e->getLine();
            $message = $e->getMessage();
            $familyHash = md5($class . ':' . $file . ':' . $line);

            // Clean stack trace
            $trace = [];
            foreach (array_slice($e->getTrace(), 0, 30) as $frame) {
                $trace[] = [
                    'file' => $frame['file'] ?? '[internal]',
                    'line' => $frame['line'] ?? 0,
                    'function' => ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? ''),
                ];
            }

            $entry = Entry::make(EntryType::EXCEPTION, [
                'class' => $class,
                'message' => $message,
                'file' => $file,
                'line' => $line,
                'code' => $e->getCode(),
                'trace' => $trace,
                'handled' => (bool) ($payload['handled'] ?? false),
            ])
            ->familyHash($familyHash)
            ->tags([$class, 'error']);

            $this->record($entry);
        });
    }
}
