<?php

namespace Spark\Pulse\Watchers;

use Spark\Events;
use Spark\Foundation\Application;
use Spark\Pulse\Entry;
use Spark\Pulse\EntryType;
use function count;
use function get_class;
use function in_array;
use function is_array;
use function is_object;

/**
 * Application Domain Event Watcher.
 *
 * Records custom application events fired during requests, jobs, or commands.
 *
 * @package Spark\Pulse\Watchers
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class EventWatcher extends Watcher
{
    public function __construct()
    {
        parent::__construct('events');
    }

    protected function boot(): void
    {
        $app = Application::$app;
        if ($app === null || !$app->bound(Events::class)) {
            return;
        }

        /** @var Events $events */
        $events = $app->get(Events::class);

        $events->addListener('*', function (string $eventName, ...$payload) {
            // Ignore internal telemetry events to prevent infinite loops
            if (str_starts_with($eventName, 'app:') || !$this->pulse->shouldRecord()) {
                return;
            }

            // Check if event is in ignore list
            $ignored = (array) config('pulse.watchers.events.ignore', []);
            if (in_array($eventName, $ignored, true) || $this->isExcluded($eventName)) {
                return;
            }

            $entry = Entry::make(EntryType::EVENT, [
                'name' => $eventName,
                'payload' => $this->formatPayload($payload),
            ])
                ->tags([$eventName]);

            $this->record($entry);
        });
    }

    private function formatPayload(array $payload): mixed
    {
        if (count($payload) === 1 && isset($payload[0])) {
            $payload = $payload[0];
        }

        if (is_object($payload)) {
            $event = ['class' => get_class($payload)];
            try {
                $event['properties'] = method_exists($payload, 'toArray') ? $payload->toArray() : get_object_vars($payload);
            } catch (\Throwable) {
                // A payload that cannot be serialized must still reach application listeners.
            }
            return $event;
        }

        return is_array($payload) ? $payload : ['value' => $payload];
    }
}
