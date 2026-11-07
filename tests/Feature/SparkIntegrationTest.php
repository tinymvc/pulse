<?php

namespace Tests\Feature;

use Spark\Facades\DB;
use Spark\Facades\Log;
use Spark\Http\Client\HttpRequest;
use Spark\Http\Client\HttpTelemetry;
use Spark\Http\Routing\Router;
use Spark\Pulse\Pulse;
use Tests\TestCase;

class SparkIntegrationTest extends TestCase
{
    protected string $dashboardPath = 'ops/inspect';

    public function test_custom_dashboard_path_keeps_its_links_and_excludes_only_its_own_routes(): void
    {
        $this->app->get(Router::class)->get('/ops/inspector', fn() => json(['ok' => true]));
        $this->get('/ops/inspect')->assertOk()->assertSee('/ops/inspect?tab=query');
        $this->get('/ops/inspect/api/entries?type=requests')->assertOk();
        $this->get('/ops/inspector')->assertOk();
        $this->assertSame(1, Pulse::instance()->storage()->count('request'));
        $this->app->setConfig('pulse.domain', 'internal.example');
        $this->get('/ops/inspect')->assertStatus(404);
    }

    public function test_real_queries_and_views_share_only_their_own_request_batch(): void
    {
        DB::exec('CREATE TABLE orders (id INTEGER)');
        file_put_contents(views_dir('orders.blade.php'), '<h1>Orders</h1>');
        $this->app->setConfig('pulse.watchers.performance.sample_rate', 100);
        $this->app->get(Router::class)->get('/orders', function () {
            DB::table('orders')->where('id', 1)->all();
            Log::info('Listing orders');
            return view('orders');
        });

        $this->get('/orders?token=private-query-value', ['Authorization' => 'private-header-value'])->assertOk();
        $this->get('/orders')->assertOk();
        $store = Pulse::instance()->storage();
        $requests = $store->get('request')['entries'];
        $this->assertCount(2, $requests);
        $this->assertFalse($requests[0]['batch_id'] === $requests[1]['batch_id']);
        foreach ($requests as $request) {
            $types = array_column($store->getByBatch($request['batch_id']), 'type');
            sort($types);
            $this->assertSame(['log', 'performance', 'query', 'request', 'view'], $types);
        }
        $this->assertSame('********', $requests[1]['content']['input']['token']);
        $this->assertSame('********', $requests[1]['content']['headers']['authorization']);
        $this->assertSame('/orders', $requests[1]['content']['uri']);
        $this->assertFalse($store->get('query')['entries'][0]['content']['is_duplicate']);
    }

    public function test_spark_http_telemetry_masks_credentials_before_storage(): void
    {
        if (!extension_loaded('curl')) {
            $this->markTestSkipped('Spark HTTP requires ext-curl.');
        }
        $request = HttpRequest::make('POST', 'https://name:private-pass@example.test/orders?token=private-query',
            ['token' => 'private-param'], ['password' => 'private-body'])
            ->withHeader('Authorization', 'Bearer private-header');
        HttpTelemetry::record($request, ['status' => 201, 'headers' => ['Set-Cookie' => 'private-cookie']], microtime(true));
        $pulse = Pulse::instance();
        $pulse->flush();
        $entries = $pulse->storage()->get('http_client')['entries'];
        $this->assertCount(1, $entries);
        $this->assertSame('https://example.test/orders', $entries[0]['content']['url']);
        $this->assertSame(201, $entries[0]['content']['status']);
        $this->assertFalse(str_contains(json_encode($entries), 'private-'));
    }
}
