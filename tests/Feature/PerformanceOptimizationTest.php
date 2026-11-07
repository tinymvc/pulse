<?php

namespace Tests\Feature;

use Spark\Pulse\Entry;
use Spark\Pulse\EntryType;
use Spark\Pulse\Recorders\ServerPerformanceRecorder;
use Spark\Pulse\Storage\MemoryStorage;
use Spark\Pulse\Watchers\Watcher;
use Tests\TestCase;

class PerformanceOptimizationTest extends TestCase
{
    public function test_entry_type_normalization_and_validation(): void
    {
        $this->assertSame(EntryType::REQUEST, EntryType::normalize('requests'));
        $this->assertSame(EntryType::COMMAND, EntryType::normalize('commands'));
        $this->assertSame(EntryType::JOB, EntryType::normalize('jobs'));
        $this->assertSame(EntryType::CACHE, EntryType::normalize('cache'));
        $this->assertSame(EntryType::EVENT, EntryType::normalize('events'));
        $this->assertSame(EntryType::EXCEPTION, EntryType::normalize('exceptions'));
        $this->assertSame(EntryType::GATE, EntryType::normalize('gates'));
        $this->assertSame(EntryType::HTTP_CLIENT, EntryType::normalize('http_client'));
        $this->assertSame(EntryType::HTTP_CLIENT, EntryType::normalize('http_clients'));
        $this->assertSame(EntryType::LOG, EntryType::normalize('logs'));
        $this->assertSame(EntryType::MAIL, EntryType::normalize('mail'));
        $this->assertSame(EntryType::MODEL, EntryType::normalize('models'));
        $this->assertSame(EntryType::QUERY, EntryType::normalize('queries'));
        $this->assertSame(EntryType::VIEW, EntryType::normalize('views'));
        $this->assertSame(EntryType::PERFORMANCE, EntryType::normalize('performance'));

        $this->assertTrue(EntryType::isValid('requests'));
        $this->assertTrue(EntryType::isValid('request'));
        $this->assertTrue(EntryType::isValid('queries'));
        $this->assertFalse(EntryType::isValid('nonexistent_type'));
    }

    public function test_memory_storage_counts_aggregation(): void
    {
        $storage = new MemoryStorage();

        $storage->store([
            Entry::make(EntryType::REQUEST, ['uri' => '/one']),
            Entry::make(EntryType::REQUEST, ['uri' => '/two']),
            Entry::make(EntryType::LOG, ['msg' => 'log entry']),
            Entry::make(EntryType::QUERY, ['sql' => 'SELECT 1']),
        ]);

        $counts = $storage->counts();

        $this->assertIsArray($counts);
        $this->assertCount(count(EntryType::all()), $counts);
        $this->assertSame(0, $counts[EntryType::LANGUAGE_FILE]);
        $this->assertSame(2, $counts[EntryType::REQUEST]);
        $this->assertSame(1, $counts[EntryType::LOG]);
        $this->assertSame(1, $counts[EntryType::QUERY]);
        $this->assertSame(0, $counts[EntryType::COMMAND]);
        $this->assertSame(0, $counts[EntryType::JOB]);
    }

    public function test_memory_units_and_byte_parsing(): void
    {
        $this->assertSame('1.5 MiB', ServerPerformanceRecorder::formatBytes(1572864));
        $this->assertSame(1572864, ServerPerformanceRecorder::parseBytes('1.5 MiB'));
        $this->assertSame(134217728, ServerPerformanceRecorder::parseBytes('128M'));
        $this->assertSame(-1, ServerPerformanceRecorder::parseBytes('-1'));
    }

    public function test_server_performance_recorder_caching(): void
    {
        $sample1 = ServerPerformanceRecorder::sample();
        $this->assertNotEmpty($sample1['sampled_at']);

        // Immediately sample again; should return exact cached result without spawning system calls
        $sample2 = ServerPerformanceRecorder::sample();
        $this->assertSame($sample1['sampled_at'], $sample2['sampled_at']);
        $this->assertSame($sample1['cpu']['usage_percent'], $sample2['cpu']['usage_percent']);
    }

    public function test_watcher_sensitive_data_masking(): void
    {
        $watcher = new class ('test') extends Watcher {
            protected function boot(): void
            {}
            public function testMask(array $data, ?array $keys = null): array
            {
                return $this->mask($data, $keys);
            }
        };

        $input = [
            'username' => 'john_doe',
            'password' => 'supersecret123',
            'api_key' => 'xyz-token',
            'nested' => [
                'token' => 'nested_token_value',
                'public_data' => 'visible',
            ],
        ];

        $masked = $watcher->testMask($input);

        $this->assertSame('john_doe', $masked['username']);
        $this->assertSame('********', $masked['password']);
        $this->assertSame('********', $masked['api_key']);
        $this->assertSame('********', $masked['nested']['token']);
        $this->assertSame('visible', $masked['nested']['public_data']);

        // Custom keys
        $customMasked = $watcher->testMask(['custom_secret' => '1234'], ['custom_secret']);
        $this->assertSame('********', $customMasked['custom_secret']);
    }

    public function test_pulse_routes_are_namespaced(): void
    {
        $router = $this->app->get(\Spark\Http\Routing\Router::class);
        $routes = $router->getRoutes();

        // Check that named routes have the 'pulse.' prefix as array keys in Spark Router
        $this->assertArrayHasKey('pulse.dashboard', $routes, 'Route pulse.dashboard should be registered');
        $this->assertArrayHasKey('pulse.api.entries', $routes, 'Route pulse.api.entries should be registered');
        $this->assertArrayHasKey('pulse.api.performance', $routes, 'Route pulse.api.performance should be registered');
    }
}
