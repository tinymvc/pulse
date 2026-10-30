<?php

namespace Tests\Feature;

use Spark\Pulse\EntryType;
use Spark\Pulse\Pulse;
use Spark\Telemetry;
use Tests\TestCase;

class WatchersAllModulesTest extends TestCase
{
    public function test_all_14_modules_are_recorded_correctly(): void
    {
        $pulse = Pulse::instance();

        // 1. Commands
        Telemetry::emit('command.finished', [
            'name' => 'migrate',
            'args' => ['--force' => true],
            'exit_code' => 0,
            'duration_ms' => 120.5,
        ]);

        // 2. Jobs
        Telemetry::emit('job.pushed', [
            'job' => new class {
                public function getDisplayName() { return 'App\Jobs\ProcessOrder'; }
                public function getPayload() { return ['order_id' => 101]; }
            },
            'queue' => 'orders',
        ]);
        Telemetry::emit('job.processed', [
            'job' => new class {
                public function getDisplayName() { return 'App\Jobs\ProcessOrder'; }
            },
            'queue' => 'orders',
            'duration_ms' => 350.0,
        ]);

        // 3. Cache
        Telemetry::emit('cache.hit', ['key' => 'settings.site', 'store' => 'redis', 'value' => 'Spark']);

        // 4. Custom Application Events (Wildcard listener)
        event('order.placed', ['order_id' => 500, 'total' => 99.99]);

        // 5. Exceptions
        Telemetry::emit('exception.occurred', [
            'exception' => new \Exception('Database timed out', 504),
            'handled' => false,
        ]);

        // 6. Gates
        Telemetry::emit('gate.checked', [
            'ability' => 'edit-settings',
            'result' => false,
            'arguments' => ['user_id' => 10],
            'user' => ['id' => 10],
        ]);

        // 7. Http Client (Outbound)
        Telemetry::emit('http.request', [
            'url' => 'https://api.stripe.com/v1/charges',
            'payload' => ['method' => 'POST'],
            'response' => ['status' => 200, 'headers' => []],
            'duration_ms' => 450.0,
        ]);

        // 8. Logs
        Telemetry::emit('log.logged', [
            'level' => 'info',
            'message' => 'User logged in',
            'context' => ['user_id' => 1],
        ]);

        // 9. Mail
        Telemetry::emit('mail.sent', [
            'to' => ['buyer@example.com'],
            'subject' => 'Thank you for your order',
            'body' => '<h1>Your receipt</h1>',
            'transport' => 'smtp',
        ]);

        // 10. Models
        Telemetry::emit('model.action', [
            'action' => 'created',
            'model' => 'App\Models\Order',
            'key' => 500,
            'table' => 'orders',
            'attributes' => ['total' => 99.99],
            'changes' => ['total' => 99.99],
        ]);

        // 11. Queries (with slow flag and duplicate detection)
        Telemetry::emit('db.queryExecuted', [
            'query' => 'SELECT * FROM products WHERE id = 1',
            'bindings' => [],
            'time' => 75.0, // > 50ms slow threshold
            'connection' => 'sqlite',
        ]);
        // Same query again -> duplicate detection
        Telemetry::emit('db.queryExecuted', [
            'query' => 'SELECT * FROM products WHERE id = 1',
            'bindings' => [],
            'time' => 1.2,
            'connection' => 'sqlite',
        ]);

        // 12. Views
        Telemetry::emit('bladeTemplateRendered', [
            'template' => 'orders.receipt',
            'path' => '/views/orders/receipt.blade.php',
            'time' => 12.4,
            'data_keys' => ['order', 'user'],
        ]);

        // Flush in-memory buffer
        $pulse->flush();

        // Assert all modules recorded records
        $this->assertSame(1, $pulse->storage()->count(EntryType::COMMAND));
        $this->assertSame(2, $pulse->storage()->count(EntryType::JOB));
        $this->assertSame(1, $pulse->storage()->count(EntryType::CACHE));
        $this->assertSame(1, $pulse->storage()->count(EntryType::EVENT));
        $this->assertSame(1, $pulse->storage()->count(EntryType::EXCEPTION));
        $this->assertSame(1, $pulse->storage()->count(EntryType::GATE));
        $this->assertSame(1, $pulse->storage()->count(EntryType::HTTP_CLIENT));
        $this->assertSame(1, $pulse->storage()->count(EntryType::LOG));
        $this->assertSame(1, $pulse->storage()->count(EntryType::MAIL));
        $this->assertSame(1, $pulse->storage()->count(EntryType::MODEL));
        $this->assertSame(2, $pulse->storage()->count(EntryType::QUERY));
        $this->assertSame(1, $pulse->storage()->count(EntryType::VIEW));

        // Check query duplicate detection
        $queries = $pulse->storage()->get(EntryType::QUERY);
        $this->assertCount(2, $queries['entries']);
        $duplicates = array_values(array_filter($queries['entries'], fn($q) => $q['content']['is_duplicate'] === true));
        $this->assertCount(1, $duplicates);
        $this->assertTrue($duplicates[0]['content']['is_duplicate']);
        $this->assertContains('duplicate', $duplicates[0]['tags']);
    }
}
