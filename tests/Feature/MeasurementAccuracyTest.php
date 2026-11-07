<?php

namespace Tests\Feature;

use Spark\Facades\DB;
use Spark\Http\Routing\Router;
use Spark\Pulse\Entry;
use Spark\Pulse\Pulse;
use Spark\Pulse\Recorders\ServerPerformanceRecorder;
use Spark\Pulse\Storage\MemoryStorage;
use Spark\Pulse\Watchers\PerformanceWatcher;
use Spark\Telemetry;
use Tests\TestCase;

class MeasurementAccuracyTest extends TestCase
{
    protected string $storageDriver = 'database';

    protected function setUp(): void
    {
        parent::setUp();
        $migration = require dirname(__DIR__, 2) . '/database/migrations/migration_2026_10_30_000000_pulse_entries.php';
        $migration->up();
        Pulse::instance()->clear();
    }

    public function test_native_query_and_view_samples_survive_storage_api_and_dashboard(): void
    {
        file_put_contents(views_dir('metric.blade.php'), '<p>Measured render</p>');
        $this->app->get(Router::class)->get('/metric', function () {
            DB::query("SELECT 'pulse_entries' AS marker");
            DB::table('pulse_entries')->where('type', 'app lookup')->all();
            return view('metric');
        });
        $this->get('/metric')->assertOk()->assertSee('Measured render');
        $request = $this->get('/pulse/api/entries?type=request')->assertOk()->json()['entries'][0];
        $this->assertTrue($request['content']['memory_peak'] > 0);
        $trace = $this->get('/pulse/api/batch/' . $request['batch_id'])->assertOk()->json()['entries'];
        $this->assertSame(['query', 'query', 'view', 'request'], array_column($trace, 'type'));
        foreach (array_slice($trace, 0, 3) as $entry) {
            $data = $entry['content'];
            $this->assertTrue($data['allocated_before_bytes'] > 0);
            $this->assertSame($data['allocated_after_bytes'] - $data['allocated_before_bytes'], $data['allocated_change_bytes']);
            $this->assertSame((float) $data['duration_ms'], (float) $entry['duration']);
        }
        $this->get('/pulse?entry=' . $trace[0]['id'])->assertOk()->assertSee('PHP allocation change');
        $this->get('/pulse?entry=' . $request['id'])->assertOk()->assertSee('Peak PHP process allocation');
        Pulse::instance()->flush();
        $this->assertSame(2, Pulse::instance()->storage()->count('query'));
    }

    public function test_sub_millisecond_and_missing_measurements_remain_distinct(): void
    {
        $this->app->setConfig('pulse.slow_thresholds.query', 0.005);
        $this->assertSame('12 ns', ServerPerformanceRecorder::formatDuration(0.000012));
        $before = memory_get_usage(true);
        Telemetry::emit('db.queryExecuted', 'SELECT 1', 0.004321, $before, 'direct');
        Telemetry::emit('db.queryExecuted', 'SELECT 2', 0.005, [], $before, 'builder');
        Telemetry::emit('db.queryExecuted', ['query' => 'SELECT unknown']);
        $pulse = Pulse::instance();
        $pulse->flush();

        $entries = $this->get('/pulse/api/entries?type=queries')->assertOk()->json()['entries'];
        $bySql = array_column(array_column($entries, 'content'), null, 'sql');
        $this->assertSame('direct', $bySql['SELECT 1']['connection']);
        $this->assertSame($before, $bySql['SELECT 1']['allocated_before_bytes']);
        $this->assertSame($before, $bySql['SELECT 2']['allocated_before_bytes']);
        $this->assertFalse(isset($bySql['SELECT unknown']['allocated_change_bytes']));
        $this->assertNull($bySql['SELECT unknown']['duration_ms']);
        $this->get('/pulse?tab=query')->assertOk()->assertSee('4.3 µs');
        $slow = $this->get('/pulse/api/entries?type=query&slow_only=1')->assertOk()->json()['entries'];
        $this->assertCount(1, $slow);
        $this->assertSame('SELECT 2', $slow[0]['content']['sql']);
        foreach ([$pulse->storage(), new MemoryStorage()] as $storage) {
            $storage->store([Entry::make('job'), Entry::make('job')->duration(0)]);
            $rows = $storage->get('job', ['slow_only' => true, 'slow_threshold' => 0])['entries'];
            $this->assertCount(1, $rows);
            $this->assertSame(0.0, $rows[0]['duration']);
        }
    }

    public function test_persisted_performance_sample_matches_current_process(): void
    {
        $prior = ServerPerformanceRecorder::sample(fresh: true);
        $buffer = str_repeat('a', 8 * 1024 * 1024);
        usleep(3000);
        $start = microtime(true);
        $watcher = new PerformanceWatcher();
        $watcher->register(Pulse::instance());
        $sample = $watcher->captureSnapshot()->content;
        $this->assertTrue($sample['memory']['app_bytes'] >= $prior['memory']['app_bytes'] + strlen($buffer));
        $this->assertTrue($sample['memory']['app_peak_bytes'] >= $sample['memory']['app_bytes']);
        $timestamp = (float) (new \DateTimeImmutable($sample['sampled_at']))->format('U.u');
        $this->assertTrue($timestamp >= $start - 0.001 && $timestamp <= microtime(true));
    }

    public function test_nested_commands_and_job_failures_keep_their_own_traces(): void
    {
        Telemetry::emit('command.starting', ['name' => 'parent']);
        $parent = Pulse::getBatchId();
        Telemetry::emit('job.processing', ['id' => 41]);
        $job = Pulse::getBatchId();
        Telemetry::emit('command.starting', ['name' => 'nested']);
        $nested = Pulse::getBatchId();
        Telemetry::emit('command.finished', ['name' => 'nested']);
        $this->assertSame($job, Pulse::getBatchId());
        Telemetry::emit('job.processed', ['id' => 41]);
        $this->assertSame($parent, Pulse::getBatchId());
        Telemetry::emit('job.failed', ['id' => 42, 'duration_ms' => 1000]); // Spark can fail before job.processing.
        $this->assertSame($parent, Pulse::getBatchId());

        $this->app->get(Router::class)->get('/trace', function () {
            Telemetry::emit('command.starting', ['name' => 'inline']);
            Telemetry::emit('command.finished', ['name' => 'inline']);
            return json(['ok' => true]);
        });
        $this->get('/trace')->assertOk();
        $this->assertSame($parent, Pulse::getBatchId());
        Telemetry::emit('command.finished', ['name' => 'parent']);
        $pulse = Pulse::instance();
        $pulse->flush();
        $store = $pulse->storage();
        $request = $store->get('request')['entries'][0];
        $this->assertSame(['command', 'request'], array_column($store->getByBatch($request['batch_id']), 'type'));
        $this->assertSame('nested', $store->getByBatch($nested)[0]['content']['name']);
        $this->assertSame('processed', $store->getByBatch($job)[0]['content']['status']);
        $this->assertSame(['command'], array_column($store->getByBatch($parent), 'type'));
        $jobs = $store->get('job')['entries'];
        $this->assertCount(2, $jobs);
        $this->assertNotSame($job, $jobs[0]['batch_id']);
        $this->assertContains('slow', $jobs[0]['tags']);
    }

    public function test_outbound_transport_failure_records_its_class_and_preserves_elapsed_time(): void
    {
        Telemetry::emit('http.request', 'https://api.test/probe?token=secret', [], ['status' => 0], 31.25,
            new \RuntimeException('Transfer failed at https://api.test/probe?token=secret'));
        $pulse = Pulse::instance();
        $pulse->flush();
        $row = $this->get('/pulse/api/entries?type=http_client')->assertOk()->json()['entries'][0];
        $this->assertSame(31.25, $row['duration']);
        $this->assertSame(\RuntimeException::class, $row['content']['error_class']);
        $this->assertContains('error', $row['tags']);
        $this->assertFalse(str_contains(json_encode($row), 'secret'));
    }

    public function test_event_observation_and_log_encoding_preserve_application_behavior(): void
    {
        $payload = new class {
            public string $status = 'ready';
            public function toArray(): array { throw new \LogicException('Unexpected serialization'); }
        };
        $called = false;
        event()->addListener('sample.ready', function () use (&$called) { $called = true; }, -1);
        event('sample.ready', [$payload]);
        $this->assertTrue($called);
        Telemetry::emit('log.logged', ['message' => 'Metric', 'context' => ['ratio' => INF, 'token' => 'private']]);
        $pulse = Pulse::instance();
        $pulse->flush();
        $this->assertSame($payload::class, $pulse->storage()->get('event')['entries'][0]['content']['payload']['class']);
        $this->assertSame(['ratio' => 'INF', 'token' => '********'], $pulse->storage()->get('log')['entries'][0]['content']['context']);
    }
}
