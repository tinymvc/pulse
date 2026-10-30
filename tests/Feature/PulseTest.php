<?php

namespace Tests\Feature;

use Spark\Http\Request;
use Spark\Http\Response;
use Spark\Pulse\EntryType;
use Spark\Pulse\Pulse;
use Spark\Pulse\Recorders\ServerPerformanceRecorder;
use Spark\Telemetry;
use Tests\TestCase;

class PulseTest extends TestCase
{
    public function test_pulse_records_request_entry_and_waterfall(): void
    {
        $pulse = Pulse::instance();

        // 1. Register a test route and call it
        $this->app->get(\Spark\Http\Routing\Router::class)->get('/api/users', function () {
            // Simulate database query within request
            Telemetry::emit('db.queryExecuted', [
                'query' => 'SELECT * FROM users WHERE active = 1',
                'bindings' => [],
                'time' => 3.4,
                'memory_before' => 1000,
                'connection' => 'sqlite',
            ]);

            return response()->json(['users' => []]);
        });

        $this->get('/api/users');

        $pulse->flush();

        // Verify request was recorded
        $requests = $pulse->storage()->get(EntryType::REQUEST);
        $this->assertCount(1, $requests['entries']);
        $reqEntry = $requests['entries'][0];
        $this->assertSame('GET', $reqEntry['content']['method']);
        $this->assertSame('/api/users', $reqEntry['content']['uri']);
        $this->assertSame(200, $reqEntry['content']['status']);

        // Verify query was recorded
        $queries = $pulse->storage()->get(EntryType::QUERY);
        $this->assertCount(1, $queries['entries']);
        $queryEntry = $queries['entries'][0];
        $this->assertSame('SELECT * FROM users WHERE active = 1', $queryEntry['content']['sql']);

        // Verify both entries share the same trace batch ID
        $this->assertSame($reqEntry['batch_id'], $queryEntry['batch_id']);

        // Verify waterfall batch fetch
        $batch = $pulse->storage()->getByBatch($reqEntry['batch_id']);
        $this->assertCount(2, $batch);
    }

    public function test_pulse_records_cache_operations(): void
    {
        $pulse = Pulse::instance();

        Telemetry::emit('cache.hit', ['key' => 'user:1', 'store' => 'file', 'value' => ['name' => 'Alice']]);
        Telemetry::emit('cache.miss', ['key' => 'user:2', 'store' => 'file']);
        Telemetry::emit('cache.written', ['key' => 'user:3', 'store' => 'file', 'value' => 'stored', 'expiration' => 3600]);

        $pulse->flush();

        $cacheEntries = $pulse->storage()->get(EntryType::CACHE);
        $this->assertCount(3, $cacheEntries['entries']);
    }

    public function test_pulse_records_exceptions_with_family_hash(): void
    {
        $pulse = Pulse::instance();

        $ex = new \RuntimeException('Something went wrong', 500);

        Telemetry::emit('exception.occurred', [
            'exception' => $ex,
            'handled' => false,
        ]);

        $pulse->flush();

        $exceptions = $pulse->storage()->get(EntryType::EXCEPTION);
        $this->assertCount(1, $exceptions['entries']);
        $entry = $exceptions['entries'][0];

        $this->assertSame('RuntimeException', $entry['content']['class']);
        $this->assertSame('Something went wrong', $entry['content']['message']);
        $this->assertNotNull($entry['family_hash']);
    }

    public function test_pulse_records_gate_checks(): void
    {
        $pulse = Pulse::instance();

        Telemetry::emit('gate.checked', [
            'ability' => 'view-dashboard',
            'result' => true,
            'arguments' => ['admin' => true],
            'user' => ['id' => 99],
        ]);

        $pulse->flush();

        $gates = $pulse->storage()->get(EntryType::GATE);
        $this->assertCount(1, $gates['entries']);
        $this->assertSame('view-dashboard', $gates['entries'][0]['content']['ability']);
        $this->assertTrue($gates['entries'][0]['content']['result']);
    }

    public function test_pulse_records_logs_and_mail(): void
    {
        $pulse = Pulse::instance();

        Telemetry::emit('log.logged', [
            'level' => 'warning',
            'message' => 'Disk space warning',
            'context' => ['used' => '85%'],
        ]);

        Telemetry::emit('mail.sent', [
            'to' => ['customer@example.com'],
            'subject' => 'Invoice #1024',
            'is_html' => true,
            'transport' => 'smtp',
        ]);

        $pulse->flush();

        $logs = $pulse->storage()->get(EntryType::LOG);
        $this->assertCount(1, $logs['entries']);
        $this->assertSame('warning', $logs['entries'][0]['content']['level']);

        $mails = $pulse->storage()->get(EntryType::MAIL);
        $this->assertCount(1, $mails['entries']);
        $this->assertSame('Invoice #1024', $mails['entries'][0]['content']['subject']);
    }

    public function test_server_performance_sampler(): void
    {
        $sample = ServerPerformanceRecorder::sample();

        $this->assertArrayHasKey('cpu', $sample);
        $this->assertArrayHasKey('memory', $sample);
        $this->assertArrayHasKey('disk', $sample);
        $this->assertArrayHasKey('runtime', $sample);
        $this->assertGreaterThanOrEqual(0, $sample['cpu']['usage_percent']);
        $this->assertNotEmpty($sample['memory']['app_formatted']);
    }

    public function test_pulse_clear_and_prune(): void
    {
        $pulse = Pulse::instance();

        Telemetry::emit('log.logged', ['level' => 'info', 'message' => 'One']);
        Telemetry::emit('log.logged', ['level' => 'info', 'message' => 'Two']);
        $pulse->flush();

        $this->assertSame(2, $pulse->storage()->count(EntryType::LOG));

        $pulse->clear();
        $this->assertSame(0, $pulse->storage()->count(EntryType::LOG));
    }
}
