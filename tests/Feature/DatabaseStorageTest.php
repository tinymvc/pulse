<?php

namespace Tests\Feature;

use Spark\Facades\Schema;
use Spark\Pulse\Entry;
use Spark\Pulse\EntryType;
use Spark\Pulse\Storage\DatabaseStorage;
use Tests\TestCase;

class DatabaseStorageTest extends TestCase
{
    private function migration(): object
    {
        return require dirname(__DIR__, 2) . '/database/migrations/migration_2026_10_30_000000_pulse_entries.php';
    }

    public function test_migrated_table_stores_entries(): void
    {
        $this->migration()->up();
        $storage = new DatabaseStorage();

        $this->assertTrue(Schema::hasTable('pulse_entries'));

        $entry1 = Entry::make(EntryType::REQUEST, [
            'method' => 'GET',
            'uri' => '/api/users',
            'status' => 200,
        ])
            ->duration(120.5)
            ->familyHash(md5('GET /api/users'))
            ->tags(['api', '200']);

        $entry2 = Entry::make(EntryType::REQUEST, [
            'method' => 'POST',
            'uri' => '/api/orders',
            'status' => 201,
        ])
            ->duration(600.0)
            ->familyHash(md5('POST /api/orders'))
            ->tags(['api', '201', 'slow']);

        $storage->store([$entry1, $entry2]);

        $this->assertSame(2, $storage->count(EntryType::REQUEST));

        // Test single-query grouped counts across all types
        $counts = $storage->counts();
        $this->assertIsArray($counts);
        $this->assertSame(2, $counts[EntryType::REQUEST]);
        $this->assertSame(0, $counts[EntryType::QUERY]);
        $this->assertCount(count(EntryType::all()), $counts);
        $this->assertSame(0, $counts[EntryType::LANGUAGE_FILE]);

        // Test find
        $found = $storage->find($entry1->id);
        $this->assertNotNull($found);
        $this->assertSame($entry1->id, $found['id']);
        $this->assertSame('GET', $found['content']['method']);

        // Test query filtering (slow_only)
        $slowEntries = $storage->get(EntryType::REQUEST, ['slow_only' => true, 'slow_threshold' => 500]);
        $this->assertCount(1, $slowEntries['entries']);
        $this->assertSame($entry2->id, $slowEntries['entries'][0]['id']);

        // Test family hash filter
        $hashEntries = $storage->get(EntryType::REQUEST, ['family_hash' => md5('GET /api/users')]);
        $this->assertCount(1, $hashEntries['entries']);
        $this->assertSame($entry1->id, $hashEntries['entries'][0]['id']);

        // Test clear
        $storage->clear();
        $this->assertSame(0, $storage->count(EntryType::REQUEST));
    }

    public function test_prune_removes_expired_records(): void
    {
        $this->migration()->up();
        $storage = new DatabaseStorage();

        $oldEntry = Entry::make(EntryType::LOG, ['message' => 'old log']);
        $oldEntry->createdAt = gmdate('Y-m-d\TH:i:s.000000\Z', time() - (48 * 3600));

        $newEntry = Entry::make(EntryType::LOG, ['message' => 'recent log']);

        $storage->store([$oldEntry, $newEntry]);
        $this->assertSame(2, $storage->count(EntryType::LOG));

        // Prune older than 24 hours
        $pruned = $storage->prune(24);
        $this->assertSame(1, $pruned);
        $this->assertSame(1, $storage->count(EntryType::LOG));

        $remaining = $storage->get(EntryType::LOG);
        $this->assertSame('recent log', $remaining['entries'][0]['content']['message']);
    }

    public function test_named_storage_uses_its_own_migrated_table(): void
    {
        $this->app->setConfig('database.connections.telemetry', ['driver' => 'sqlite', 'file' => $this->storagePath . '/pulse.sqlite']);
        $this->app->setConfig('pulse.storage.connection', 'telemetry');
        $this->app->setConfig('pulse.storage.table', 'observations');
        $this->migration()->up();
        $this->assertFalse(Schema::hasTable('observations'));
        $storage = new DatabaseStorage();
        $storage->store(Entry::make('log'));
        $this->assertSame(1, $storage->count());
        $this->migration()->down();
        $this->assertFalse(Schema::hasTable('observations'));
        $this->assertThrows(\PDOException::class, fn() => $storage->count());
    }

    public function test_cursor_and_literal_search_match_in_both_drivers(): void
    {
        $this->migration()->up();
        $one = Entry::make('log', ['message' => 'Usage: 40%_ok']);
        $two = Entry::make('log', ['message' => 'Usage: 40%_ok']);
        $two->createdAt = $one->createdAt;
        foreach ([new DatabaseStorage(), new \Spark\Pulse\Storage\MemoryStorage()] as $storage) {
            $storage->store([$one, $two, Entry::make('log', ['message' => 'other'])]);
            $options = ['search' => '%_OK', 'limit' => 1];
            $first = $storage->get('log', $options);
            $this->assertSame(2, $first['total']);
            $row = $first['entries'][0];
            $second = $storage->get('log', $options + ['before' => $row['created_at'], 'before_id' => $row['id']]);
            $this->assertCount(1, $second['entries']);
            $this->assertFalse($second['has_more']);
            $this->assertFalse($row['id'] === $second['entries'][0]['id']);
        }
    }
}
