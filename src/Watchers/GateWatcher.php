<?php

namespace Spark\Pulse\Watchers;

use Spark\Pulse\Entry;
use Spark\Pulse\EntryType;
use Spark\Telemetry;

/**
 * Gate Authorization Watcher.
 *
 * Records Gate ability checks, arguments, user context, and authorization outcomes.
 *
 * @package Spark\Pulse\Watchers
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class GateWatcher extends Watcher
{
    public function __construct()
    {
        parent::__construct('gates');
    }

    protected function boot(): void
    {
        Telemetry::listen('gate.checked', function (array $payload) {
            $ability = (string) ($payload['ability'] ?? '');
            $result = (bool) ($payload['result'] ?? false);
            $user = $payload['user'] ?? null;

            $userInfo = null;
            if ($user !== null) {
                $userInfo = [
                    'id' => is_object($user) && method_exists($user, 'getKey') ? $user->getKey() : ($user->id ?? (is_scalar($user) ? $user : null)),
                    'class' => is_object($user) ? get_class($user) : null,
                ];
            }

            $tags = [$ability, $result ? 'allowed' : 'denied'];

            $entry = Entry::make(EntryType::GATE, [
                'ability' => $ability,
                'result' => $result,
                'user' => $userInfo,
                'arguments' => $this->mask((array) ($payload['arguments'] ?? [])),
            ])
            ->tags($tags);

            $this->record($entry);
        });
    }
}
