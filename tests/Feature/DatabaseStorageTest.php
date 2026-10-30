<?php

namespace Tests\Feature;

use Spark\Facades\Schema;
use Spark\Pulse\Entry;
use Spark\Pulse\EntryType;
use Spark\Pulse\Storage\DatabaseStorage;
use Tests\TestCase;

class DatabaseStorageTest extends TestCase
{
    public function test_ensure_installed_creates_table_and_stores_entries(): void
    {
        $storage = new DatabaseStorage();
        $storage->ensureInstalled();

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
        $storage = new DatabaseStorage();
        $storage->ensureInstalled();

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
}
