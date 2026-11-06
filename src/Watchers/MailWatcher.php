<?php

namespace Spark\Pulse\Watchers;

use Spark\Pulse\Entry;
use Spark\Pulse\EntryType;
use Spark\Telemetry;

/**
 * Outbound Mail Watcher.
 *
 * Records dispatched emails, recipients, subject lines, bodies, and delivery statuses.
 *
 * @package Spark\Pulse\Watchers
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class MailWatcher extends Watcher
{
    public function __construct()
    {
        parent::__construct('mail');
    }

    protected function boot(): void
    {
        Telemetry::listen('mail.sent', function (array $payload) {
            $to = (array) ($payload['to'] ?? []);
            $subject = (string) ($payload['subject'] ?? '(no subject)');

            $entry = Entry::make(EntryType::MAIL, [
                'status' => 'sent',
                'to' => $to,
                'cc' => (array) ($payload['cc'] ?? []),
                'bcc' => (array) ($payload['bcc'] ?? []),
                'reply_to' => (array) ($payload['reply_to'] ?? []),
                'subject' => $subject,
                'body' => $payload['body'] ?? '',
                'is_html' => (bool) ($payload['is_html'] ?? true),
                'transport' => $payload['transport'] ?? 'smtp',
            ])
                ->tags(['sent', ...$to]);

            $this->record($entry);
        });

        Telemetry::listen('mail.failed', function (array $payload) {
            $to = (array) ($payload['to'] ?? []);
            $subject = (string) ($payload['subject'] ?? '(no subject)');

            $entry = Entry::make(EntryType::MAIL, [
                'status' => 'failed',
                'to' => $to,
                'subject' => $subject,
                'error' => $payload['error'] ?? 'Unknown error',
                'transport' => $payload['transport'] ?? 'smtp',
            ])
                ->tags(['failed', 'error', ...$to]);

            $this->record($entry);
        });
    }
}
