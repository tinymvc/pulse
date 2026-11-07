<?php

namespace Tests\Feature;

use Closure;
use Exception;
use Spark\Facades\Route;
use Spark\Http\Request;
use Spark\Http\Response;
use Spark\Pulse\Pulse;
use Spark\Telemetry;
use Tests\TestCase;
use Throwable;

class ExceptionRecordingTest extends TestCase
{
    protected array $globalMiddleware = [RenderTestFailure::class];
    protected string $storageDriver = 'database';

    protected function setUp(): void
    {
        parent::setUp();
        (require dirname(__DIR__, 2) . '/database/migrations/migration_2026_10_30_000000_pulse_entries.php')->up();
        Pulse::instance()->clear();
    }

    public function test_middleware_rendered_exception_is_visible_in_the_dashboard_and_request_trace(): void
    {
        $message = 'This route is not used. Please use the store method to handle login requests.';
        Route::get('test', static fn() => throw new Exception($message));

        $this->get('/test')->assertStatus(500)->assertSee('Custom error page');
        $request = $this->get('/pulse/api/entries?type=request')->assertOk()->json('entries.0');
        $entries = $this->get('/pulse/api/entries?type=exceptions')->assertOk()->json('entries');
        $this->assertCount(1, $entries);
        $entry = $entries[0];
        $this->assertSame(500, $request['content']['status']);
        $this->assertSame($request['batch_id'], $entry['batch_id']);
        $this->assertSame(Exception::class, $entry['content']['class']);
        $this->assertSame($message, $entry['content']['message']);
        $this->assertSame(__FILE__, $entry['content']['file']);
        $this->assertTrue($entry['content']['line'] > 0);
        $this->assertTrue($entry['content']['trace'] !== []);
        $this->assertTrue($entry['content']['handled']);
        $this->get('/pulse?tab=exception')->assertOk()->assertSee($message);
    }

    public function test_failed_statuses_and_response_shortcuts_do_not_create_exceptions(): void
    {
        Route::get('plain', static fn() => new Response('Unavailable', 503));
        Route::get('abort', static fn() => abort(500));
        $this->get('/plain')->assertStatus(503);
        $this->get('/abort')->assertStatus(500);
        Route::get('recover', static fn() => throw new Exception('Recovered'));
        $this->get('/recover')->assertStatus(422);
        $this->get('/pulse/api/entries?type=exceptions')->assertOk()->assertJsonCount(0, 'entries');
    }

    public function test_framework_report_and_response_are_counted_once_each_time_a_route_fails(): void
    {
        $error = new Exception('Failed twice');
        $this->app->withExceptions([Exception::class => static fn() => new Response('Framework error page', 500)]);
        Route::get('framework', static fn() => throw $error);

        $this->get('/framework')->assertStatus(500)->assertSee('Framework error page');
        $this->get('/framework')->assertStatus(500);
        $entries = $this->get('/pulse/api/entries?type=exceptions')->assertOk()->json('entries');
        $this->assertCount(2, $entries);
        $this->assertTrue($entries[0]['batch_id'] !== $entries[1]['batch_id']);
    }

    public function test_application_reporting_the_same_error_and_a_recovered_route_do_not_duplicate_it(): void
    {
        Route::get('test', static function () {
            $error = new Exception('Reported explicitly');
            Telemetry::emit('exception.occurred', ['exception' => $error, 'handled' => true]);
            throw $error;
        });
        $this->get('/test')->assertStatus(500);
        $this->get('/pulse/api/entries?type=exceptions')->assertOk()->assertJsonCount(1, 'entries');
    }

    public function test_terminal_tracer_reporting_an_unhandled_exception_keeps_one_entry(): void
    {
        $error = new Exception('Unhandled');
        Route::get('unhandled', static fn() => throw $error);
        try {
            $this->get('/unhandled');
        } catch (Exception $caught) {
            $this->assertSame($error, $caught);
            // Tracer::handleException emits this event after handle() unwinds, then exits PHP.
            Telemetry::emit('exception.occurred', ['exception' => $caught, 'type' => 'Exception', 'handled' => false]);
        }
        Pulse::instance()->flush();
        $this->get('/pulse/api/entries?type=exceptions')->assertOk()
            ->assertJsonCount(1, 'entries')->assertJsonPath('entries.0.content.handled', false);
    }

    public function test_middleware_capture_respects_exception_exclusions_and_ignored_paths(): void
    {
        Route::get('test', static fn() => throw new Exception('Excluded'));
        $this->app->setConfig('pulse.watchers.exceptions.exclude', [Exception::class]);
        $this->get('/test')->assertStatus(500);
        $this->app->setConfig('pulse.watchers.exceptions.exclude', []);
        $this->app->setConfig('pulse.ignore_paths', ['test']);
        $this->get('/test')->assertStatus(500);
        $this->get('/pulse/api/entries?type=exceptions')->assertOk()->assertJsonCount(0, 'entries');
    }
}

final class RenderTestFailure
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (!$request->is('test') && !$request->is('recover')) {
            return $next($request);
        }
        try {
            return $next($request);
        } catch (Throwable) {
            return new Response('Custom error page', $request->is('recover') ? 422 : 500);
        }
    }
}
