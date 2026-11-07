<?php

namespace Tests\Feature;

use RuntimeException;
use Spark\Facades\Log;
use Spark\Http\Routing\Router;
use Spark\Pulse\EntryType;
use Spark\Pulse\Pulse;
use Spark\Queue\Dispatchable;
use Spark\Queue\Job;
use Spark\Queue\Queue;
use Spark\Telemetry;
use Tests\TestCase;

class WatcherExclusionsTest extends TestCase
{
    public function test_job_class_exclusion_covers_dispatch_success_and_failed_retries(): void
    {
        $this->app->setConfig('pulse.watchers.jobs.exclude', [PublishRealtime::class, 'App\Jobs\Internal\*']);
        $queue = new Queue(['driver' => 'file', 'path' => $this->storagePath . '/jobs']);
        $this->assertTrue($queue->push(PublishRealtime::make([false])));
        $this->assertTrue($queue->push(PublishRealtime::make([true])));

        ob_start();
        try {
            // Process the successful job and both attempts at the failing job.
            for ($i = 0; $i < 3; $i++) {
                $queue->work(once: true, delay: 0, tries: 2);
            }
        } finally {
            ob_end_clean();
        }

        $this->assertCount(1, $queue->getFailedJobs());
        $this->assertTrue($queue->push(Job::make(['App\Jobs\Internal\Sync', 'handle'])));
        $this->assertTrue($queue->push(Job::make(PublishRealtime::class . 'Digest')));
        $aliased = new class (PublishRealtime::class . '::handle') extends Job {
            public function getDisplayName(): string
            {
                return 'Publish update';
            }
        };
        Telemetry::emit('job.pushed', ['job' => $aliased]);
        $pulse = Pulse::instance();
        $pulse->flush();
        $jobs = $pulse->storage()->get('job')['entries'];
        $this->assertCount(1, $jobs);
        $this->assertSame(PublishRealtime::class . 'Digest', $jobs[0]['content']['name']);
        $this->assertSame(3, $pulse->storage()->count('log'));
    }

    public function test_request_and_performance_exclusions_are_independent(): void
    {
        $this->app->setConfig('pulse.watchers.requests.exclude', ['probes/*']);
        $this->app->setConfig('pulse.watchers.performance.exclude', ['/metrics']);
        $this->app->setConfig('pulse.watchers.performance.sample_rate', 100);
        $router = $this->app->get(Router::class);
        foreach (['/probes/ready', '/metrics'] as $path) {
            $router->get($path, function () {
                Log::info('Health check');
                return json(['ready' => true]);
            });
        }

        $store = Pulse::instance()->storage();
        $this->get('/probes/ready?verbose=1')->assertOk();
        $this->assertSame(0, $store->count('request'));
        $this->assertSame(1, $store->count('performance'));
        $this->get('/metrics?verbose=1')->assertOk();
        $this->assertSame('/metrics', $store->get('request')['entries'][0]['content']['uri']);
        $this->assertSame(1, $store->count('performance'));
        $this->assertSame(2, $store->count('log'));
    }

    public function test_cache_exclusions_filter_individual_keys_and_query_exclusions_skip_duplicate_tracking(): void
    {
        $this->app->setConfig('pulse.watchers.cache.exclude', ['session:*']);
        foreach (['cache.hit', 'cache.miss', 'cache.written'] as $event) {
            Telemetry::emit($event, ['key' => 'session:42']);
        }
        Telemetry::emit('cache.forgotten', ['keys' => ['session:42']]);
        Telemetry::emit('cache.forgotten', ['keys' => ['session:42', 'public', 'Session:42']]);

        $sql = 'SELECT * FROM audit';
        $this->app->setConfig('pulse.watchers.queries.exclude', ['*FROM audit', 'replica']);
        Telemetry::emit('db.queryExecuted', $sql, 0.1, [], 0, 'main');
        Telemetry::emit('db.queryExecuted', ['query' => 'SELECT 1', 'connection' => 'replica']);
        $this->app->setConfig('pulse.watchers.queries.exclude', []);
        Telemetry::emit('db.queryExecuted', $sql, 0.1, 0, 'main');

        $pulse = Pulse::instance();
        $pulse->flush();
        $cache = $pulse->storage()->get('cache')['entries'];
        $this->assertCount(1, $cache);
        $this->assertSame(['public', 'Session:42'], $cache[0]['content']['keys']);
        $queries = $pulse->storage()->get('query')['entries'];
        $this->assertCount(1, $queries);
        $this->assertSame(1, $queries[0]['content']['duplicate_count']);
    }

    public function test_watchers_match_their_documented_identifiers(): void
    {
        $rules = [
            'commands' => ['sync:*'],
            'events' => ['realtime.*'],
            'exceptions' => [RuntimeException::class],
            'gates' => ['view-metrics'],
            'http_client' => ['telemetry.test', 'https://example.test/health'],
            'logs' => ['debug', 'Heartbeat *'],
            'mail' => ['Digest *', '*@internal.test'],
            'models' => ['App\Models\Metric'],
            'views' => ['reports_*', '/cache/internal/*'],
            'language_files' => ['lang/en/*'],
        ];
        foreach ($rules as $watcher => $patterns) {
            $this->app->setConfig("pulse.watchers.$watcher.exclude", $patterns);
        }

        Telemetry::emit('command.finished', ['name' => 'sync:metrics']);
        event('realtime.ping');
        Telemetry::emit('exception.occurred', ['exception' => new RuntimeException()]);
        Telemetry::emit('gate.checked', ['ability' => 'view-metrics']);
        Telemetry::emit('http.request', 'https://telemetry.test/event');
        Telemetry::emit('http.request', ['url' => 'https://user:pass@example.test/health?key=test#ping']);
        Telemetry::emit('log.logged', ['level' => 'debug', 'message' => 'Details']);
        Telemetry::emit('log.logged', ['level' => 'info', 'message' => 'Heartbeat OK']);
        foreach (['mail.sent', 'mail.failed'] as $event) {
            Telemetry::emit($event, ['subject' => 'Digest ready']);
            Telemetry::emit($event, ['to' => ['first@example.test', 'bot@internal.test']]);
        }
        Telemetry::emit('model.action', ['model' => 'App\Models\Metric']);
        Telemetry::emit('bladeTemplateRendered', '/cache/reports_daily_' . str_repeat('a', 32) . '.php');
        Telemetry::emit('bladeTemplateRendered', ['path' => '/cache/internal/nav.php', 'template' => 'nav']);
        Telemetry::emit('translator.loadedLanguageFile', root_dir() . '/lang/en/common.php', 0.1, memory_get_usage(true));

        $pulse = Pulse::instance();
        $pulse->flush();
        foreach (array_keys($rules) as $watcher) {
            $this->assertSame(0, $pulse->storage()->count(EntryType::normalize($watcher)), $watcher);
        }
        // Dots in patterns are literal and class rules match exactly.
        event('realtimeXping');
        Telemetry::emit('model.action', ['model' => 'App\Models\MetricDaily']);
        $pulse->flush();
        $this->assertSame(1, $pulse->storage()->count('event'));
        $this->assertSame(1, $pulse->storage()->count('model'));
    }
}

class PublishRealtime
{
    use Dispatchable;

    public function __construct(private bool $fail)
    {
    }

    public function handle(): void
    {
        Log::info('Publishing realtime update');
        if ($this->fail) {
            throw new RuntimeException('Publish failed');
        }
    }
}
