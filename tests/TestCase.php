<?php

namespace Tests;

use Spark\Foundation\Application;
use Spark\Pulse\PulseServiceProvider;
use Spark\Testing\ApplicationTestCase;

abstract class TestCase extends ApplicationTestCase
{
    protected string $dashboardPath = 'pulse';

    protected function createApplication(): Application
    {
        mkdir($this->storagePath . '/resources/views', 0755, true);
        $app = Application::create(path: $this->storagePath, config: [
            'app' => [
                'debug' => true,
                'key' => 'core-test-only-key-at-least-32-characters',
                'url' => 'http://localhost',
                'timezone' => 'UTC',
                'storage_dir' => $this->storagePath,
                'temp_dir' => $this->storagePath . '/temp',
                'views_dir' => $this->storagePath . '/resources/views',
            ],
            'database' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
            ],
            'pulse' => [
                'enabled' => true,
                'path' => $this->dashboardPath,
                'watchers' => ['performance' => ['sample_rate' => 0]],
                'storage' => [
                    'driver' => 'memory',
                ],
            ],
        ], providers: [
            PulseServiceProvider::class,
        ]);

        $app->boot();

        return $app;
    }
}
