<?php

namespace Tests;

use Spark\Foundation\Application;
use Spark\Pulse\PulseServiceProvider;
use Spark\Testing\ApplicationTestCase;

abstract class TestCase extends ApplicationTestCase
{
    protected function testStorageDirectory(): string
    {
        return __DIR__ . '/.cache';
    }

    protected function createApplication(): Application
    {
        $app = Application::create(path: $this->storagePath, config: [
            'app' => [
                'debug' => true,
                'key' => 'core-test-only-key-at-least-32-characters',
                'url' => 'http://localhost',
                'timezone' => 'UTC',
                'storage_dir' => $this->storagePath,
                'temp_dir' => $this->storagePath . '/temp',
                'views_dir' => dirname(__DIR__, 2) . '/tinycore/src/Foundation/resources/views',
            ],
            'database' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
            ],
            'pulse' => [
                'enabled' => true,
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
