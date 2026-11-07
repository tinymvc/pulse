<?php

namespace Tests\Feature;

use DateTime;
use Spark\Carbon;
use Spark\Contracts\Support\Arrayable;
use Spark\Database\Model;
use Spark\Facades\DB;
use Spark\Http\Routing\Router;
use Spark\Pulse\Entry;
use Spark\Pulse\Pulse;
use Spark\Pulse\Recorders\ServerPerformanceRecorder;
use Spark\Pulse\Storage\MemoryStorage;
use Spark\Pulse\Watchers\PerformanceWatcher;
use Spark\Support\Collection;
use Spark\Support\LazyCollection;
use Spark\Telemetry;
use Spark\Url;
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

    public function test_model_save_records_cast_dates_and_original_values_in_utc(): void
    {
        DB::exec('CREATE TABLE members (id INTEGER PRIMARY KEY, onboarded_at TEXT, verified_at TEXT, password TEXT, created_at TEXT, updated_at TEXT)');
        DB::table('members')->insert([
            'id' => 1,
            'onboarded_at' => '2026-11-01T06:46:50+06:00',
            'password' => 'private',
            'created_at' => '2026-10-31T20:00:00-04:00',
        ]);

        $member = TimestampedMember::findOrFail(1);
        $member->onboarded_at = new Carbon('2026-11-02 01:05:07.123456', 'Asia/Dhaka');
        $member->verified_at = new DateTime('2026-11-03T01:02:03.456789-04:00');
        $this->assertTrue($member->save());
        Pulse::instance()->flush();

        $entry = $this->get('/pulse/api/entries?type=models')->assertOk()->json('entries.0');
        $this->assertSame('updated', $entry['content']['action']);
        $this->assertSame(TimestampedMember::class, $entry['content']['model']);
        $attributes = $entry['content']['attributes'];
        $this->assertSame('2026-11-01T19:05:07.123456Z', $attributes['onboarded_at']);
        $this->assertSame('2026-11-03T05:02:03.456789Z', $attributes['verified_at']);
        $this->assertSame('2026-11-01T00:00:00.000000Z', $attributes['created_at']);
        $this->assertSame('********', $attributes['password']);
        $this->assertSame('2026-11-01T00:46:50.000000Z', $entry['content']['changes']['onboarded_at']);
        $this->assertNull($entry['content']['changes']['verified_at']);
        $this->assertSame('Asia/Dhaka', $member->onboarded_at->getTimezone()->getName());
        $this->assertSame('-04:00', $member->verified_at->format('P'));
        $this->get('/pulse?entry=' . $entry['id'])->assertOk()->assertSee($attributes['onboarded_at']);
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
        Telemetry::emit(
            'http.request',
            'https://api.test/probe?token=secret',
            [],
            ['status' => 0],
            31.25,
            new \RuntimeException('Transfer failed at https://api.test/probe?token=secret')
        );
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
            public function toArray(): array
            {
                throw new \LogicException('Unexpected serialization');
            }
        };
        $called = false;
        event()->addListener('sample.ready', function () use (&$called) {
            $called = true;
        }, -1);
        event('sample.ready', [$payload]);
        $this->assertTrue($called);
        Telemetry::emit('log.logged', ['message' => 'Metric', 'context' => ['ratio' => INF, 'token' => 'private']]);
        $pulse = Pulse::instance();
        $pulse->flush();
        $this->assertSame($payload::class, $pulse->storage()->get('event')['entries'][0]['content']['payload']['class']);
        $this->assertSame(['ratio' => 'INF', 'token' => '********'], $pulse->storage()->get('log')['entries'][0]['content']['context']);
    }

    public function test_arrayable_log_values_are_masked_and_formatted_before_storage(): void
    {
        $payload = new class implements Arrayable {
            public function toArray(): array
            {
                return [
                    'details' => new Collection([
                        'api-key' => 'private',
                        'url' => new Url('https://example.test/orders?page=2'),
                        'at' => new Carbon('2026-11-01T06:46:50.123456+06:00'),
                    ])
                ];
            }
        };
        Telemetry::emit('log.logged', ['message' => 'Order', 'context' => ['order' => $payload]]);
        Pulse::instance()->flush();

        $entry = $this->get('/pulse/api/entries?type=logs')->assertOk()->json('entries.0');
        $this->assertSame([
            'api-key' => '********',
            'url' => 'https://example.test/orders?page=2',
            'at' => '2026-11-01T00:46:50.123456Z',
        ], $entry['content']['context']['order']['details']);
    }

    public function test_failed_serializers_preserve_the_entry_and_arrayable_previews_stay_bounded(): void
    {
        $broken = new class implements Arrayable {
            public function toArray(): array
            {
                throw new \RuntimeException('Serializer failed');
            }
        };
        $large = new class implements Arrayable {
            public function toArray(): array
            {
                return range(1, 1500);
            }
        };
        $consumed = false;
        $lazy = new LazyCollection(static function () use (&$consumed) {
            $consumed = true;
            yield 'item';
        });
        Telemetry::emit('log.logged', [
            'message' => 'Preview',
            'context' => [
                'objects' => new Collection(['broken' => $broken, 'ok' => 42]),
                'stream' => $lazy,
                'large' => $large,
            ]
        ]);
        Pulse::instance()->flush();

        $response = $this->get('/pulse/api/entries?type=logs')->assertOk()->assertJsonCount(1, 'entries');
        $context = $response->json('entries.0.content.context');
        $this->assertSame(['broken' => get_debug_type($broken), 'ok' => 42], $context['objects']);
        $this->assertSame(LazyCollection::class, $context['stream']);
        $this->assertFalse($consumed);
        $this->assertSame('[truncated]', $context['large']['…']);
        $this->assertTrue(count($context['large']) <= 1024);
    }
}

final class TimestampedMember extends Model
{
    protected string $table = 'members';
    protected array $fillable = ['onboarded_at', 'verified_at', 'password'];
    protected array $casts = ['onboarded_at' => 'datetime'];
}
