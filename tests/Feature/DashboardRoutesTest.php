<?php

namespace Tests\Feature;

use Spark\Pulse\Entry;
use Spark\Pulse\EntryType;
use Spark\Pulse\Pulse;
use Tests\TestCase;

class DashboardRoutesTest extends TestCase
{
    public function test_dashboard_page_loads_successfully(): void
    {
        $response = $this->get('/pulse');
        $response->assertOk();
        $response->assertSee('Spark Pulse');
        $response->assertSee('Requests');
        $response->assertSee('Queries');
    }

    public function test_api_performance_returns_server_metrics(): void
    {
        $response = $this->get('/pulse/api/performance');
        $response->assertOk();

        $data = $response->json();
        $this->assertArrayHasKey('server', $data);
        $this->assertArrayHasKey('cpu', $data['server']);
        $this->assertArrayHasKey('memory', $data['server']);
        $this->assertArrayHasKey('disk', $data['server']);
        $this->assertArrayHasKey('runtime', $data['server']);
        $this->assertArrayHasKey('counts', $data);
        $this->assertArrayHasKey('recording', $data);
    }

    public function test_api_entries_and_details_endpoints(): void
    {
        $pulse = Pulse::instance();
        $entry = Entry::make(EntryType::REQUEST, [
            'method' => 'GET',
            'uri' => '/api/test',
            'status' => 200,
        ])->duration(45.2);

        $pulse->record($entry);
        $pulse->flush();

        // Query entries by type
        $response = $this->get('/pulse/api/entries?type=requests');
        $response->assertOk();
        $data = $response->json();
        $this->assertArrayHasKey('entries', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('has_more', $data);
        $this->assertGreaterThanOrEqual(1, count($data['entries']));

        // Query single entry detail
        $detailResponse = $this->get('/pulse/api/entries/' . $entry->id);
        $detailResponse->assertOk();
        $detailData = $detailResponse->json();
        $this->assertSame($entry->id, $detailData['id']);
        $this->assertSame('GET', $detailData['content']['method']);

        // Query batch traces
        $batchResponse = $this->get('/pulse/api/batch/' . $entry->batchId);
        $batchResponse->assertOk();
        $batchData = $batchResponse->json();
        $this->assertArrayHasKey('batch_id', $batchData);
        $this->assertArrayHasKey('count', $batchData);
        $this->assertArrayHasKey('entries', $batchData);
    }

    public function test_api_toggle_and_clear_endpoints(): void
    {
        $pulse = Pulse::instance();
        $this->assertTrue($pulse->isRecording());

        // Toggle recording to off
        $toggleResponse = $this->post('/pulse/api/toggle');
        $toggleResponse->assertOk();
        $toggleData = $toggleResponse->json();
        $this->assertFalse($toggleData['recording']);
        $this->assertFalse($pulse->isRecording());

        // Toggle recording back on
        $toggleResponse2 = $this->post('/pulse/api/toggle');
        $toggleResponse2->assertOk();
        $this->assertTrue($toggleResponse2->json()['recording']);
        $this->assertTrue($pulse->isRecording());

        // Clear entries
        $clearResponse = $this->post('/pulse/api/clear');
        $clearResponse->assertOk();
        $this->assertTrue($clearResponse->json()['success']);
    }

    public function test_authorization_middleware_blocks_unauthorized_access(): void
    {
        // Enforce an auth callback that always denies access
        Pulse::instance()->auth(fn($req) => false);

        $response = $this->get('/pulse');
        $response->assertStatus(403);
    }
}
