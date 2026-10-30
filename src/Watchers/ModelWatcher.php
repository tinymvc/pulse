<?php

namespace Spark\Pulse\Watchers;

use Spark\Pulse\Entry;
use Spark\Pulse\EntryType;
use Spark\Telemetry;

/**
 * ORM Model Action Watcher.
 *
 * Records model creation, update, and deletion lifecycle actions.
 *
 * @package Spark\Pulse\Watchers
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class ModelWatcher extends Watcher
{
    public function __construct()
    {
        parent::__construct('models');
    }

    protected function boot(): void
    {
        Telemetry::listen('model.action', function (array $payload) {
            $model = (string) ($payload['model'] ?? 'Model');
            $action = (string) ($payload['action'] ?? 'saved');
            $key = $payload['key'] ?? null;

            // Short model basename
            $shortName = substr(strrchr('\\' . $model, '\\'), 1);

            $entry = Entry::make(EntryType::MODEL, [
                'action' => $action,
                'model' => $model,
                'short_name' => $shortName,
                'key' => $key,
                'table' => $payload['table'] ?? null,
                'attributes' => $this->mask((array) ($payload['attributes'] ?? [])),
                'changes' => $this->mask((array) ($payload['changes'] ?? [])),
            ])
            ->tags([$shortName, $action]);

            $this->record($entry);
        });
    }
}
