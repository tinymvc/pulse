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

        $this->get('/pulse/api/performance');
        $this->withHeaders(['X-CSRF-TOKEN' => csrf_token()]);

        // Toggle recording to off
        $toggleResponse = $this->post('/pulse/api/toggle');
        $toggleResponse->assertOk();
        $toggleData = $toggleResponse->json();
        $this->assertFalse($toggleData['recording']);
        $this->assertFalse($pulse->isRecording());
        $this->assertFalse((new Pulse())->isRecording());

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

    public function test_web_dashboard_escapes_payloads_and_remains_quiet_on_refresh(): void
    {
        $pulse = Pulse::instance();
        $entry = Entry::make('log', ['message' => '<b>test marker</b>']);
        $pulse->record($entry);
        $pulse->flush();
        $this->get('/pulse?tab=log&search=marker')->assertOk()->assertSee('&lt;b&gt;test marker&lt;/b&gt;');
        $this->get('/pulse?entry=' . $entry->id)->assertOk()->assertSee('Execution trace')->assertSee('&lt;b&gt;test marker&lt;/b&gt;');
        $this->get('/pulse', ['X-Pulse-Refresh' => '1'])->assertOk()->assertHeader('Cache-Control', 'private, no-store');
        $pulse->flush();
        $this->assertSame(1, $pulse->storage()->count());
    }

    public function test_dashboard_actions_require_access_and_valid_input(): void
    {
        $pulse = Pulse::instance();
        $this->app->setConfig('app.env', 'production');
        $this->get('/pulse/api/entries')->assertStatus(403);
        $this->post('/pulse/api/clear')->assertStatus(403);
        $pulse->auth(fn() => true);
        $this->get('/pulse')->assertOk();
        $this->post('/pulse/api/clear')->assertStatus(419);
        $this->withHeaders(['X-CSRF-TOKEN' => csrf_token()]);
        $this->post('/pulse/api/prune', ['hours' => -1])->assertStatus(422);
        $this->get('/pulse/api/entries?type[]=invalid')->assertStatus(422);
    }
}
