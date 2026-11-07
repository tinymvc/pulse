<?php

namespace Spark\Pulse;

use Spark\Console\Commands;
use Spark\Foundation\Providers\ServiceProvider;
use Spark\Http\Routing\Router;
use Spark\Pulse\Console\CheckCommand;
use Spark\Pulse\Console\ClearCommand;
use Spark\Pulse\Console\InstallCommand;
use Spark\Pulse\Console\PruneCommand;
use Spark\Pulse\Http\Middleware\AuthorizePulse;
use Spark\Pulse\Http\Middleware\VerifyCsrfToken;
use Spark\Pulse\Watchers\CacheWatcher;
use Spark\Pulse\Watchers\CommandWatcher;
use Spark\Pulse\Watchers\EventWatcher;
use Spark\Pulse\Watchers\ExceptionWatcher;
use Spark\Pulse\Watchers\GateWatcher;
use Spark\Pulse\Watchers\HttpClientWatcher;
use Spark\Pulse\Watchers\JobWatcher;
use Spark\Pulse\Watchers\LanguageFileWatcher;
use Spark\Pulse\Watchers\LogWatcher;
use Spark\Pulse\Watchers\MailWatcher;
use Spark\Pulse\Watchers\ModelWatcher;
use Spark\Pulse\Watchers\PerformanceWatcher;
use Spark\Pulse\Watchers\QueryWatcher;
use Spark\Pulse\Watchers\RequestWatcher;
use Spark\Pulse\Watchers\ViewWatcher;
use Spark\Pulse\Watchers\Watcher;
use Spark\Telemetry;

/**
 * Service Provider for Spark Pulse.
 *
 * Bootstraps Pulse watchers, console commands, route groups, and storage.
 *
 * @package Spark\Pulse
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class PulseServiceProvider extends ServiceProvider
{
    /** @var array<class-string<Watcher>> */
    protected array $watchers = [
        RequestWatcher::class,
        CommandWatcher::class,
        JobWatcher::class,
        CacheWatcher::class,
        EventWatcher::class,
        ExceptionWatcher::class,
        GateWatcher::class,
        HttpClientWatcher::class,
        LogWatcher::class,
        MailWatcher::class,
        ModelWatcher::class,
        QueryWatcher::class,
        ViewWatcher::class,
        LanguageFileWatcher::class,
        PerformanceWatcher::class,
    ];

    /**
     * {@inheritdoc}
     */
    public function register(): void
    {
        $defaults = require __DIR__ . '/../config/pulse.php';
        $configured = (array) $this->app->getConfig('pulse', []);
        $config = array_replace_recursive($defaults, $configured);
        foreach (['middleware', 'ignore_paths', 'ignore_commands'] as $list) {
            $config[$list] = $configured[$list] ?? $defaults[$list];
        }
        $config['sensitive_keys'] = array_unique([...$defaults['sensitive_keys'], ...($configured['sensitive_keys'] ?? [])]);

        $this->app->setConfig('pulse', $config);

        Watcher::resetSensitiveMap();

        $this->app->singleton(Pulse::class, fn() => new Pulse());
        $this->app->alias('pulse', Pulse::class);
    }

    /**
     * {@inheritdoc}
     */
    public function boot(): void
    {
        if ($this->app->bound(Commands::class)) {
            /** @var Commands $commands */
            $commands = $this->app->get(Commands::class);
            $commands->addCommand('pulse:install', [InstallCommand::class, 'handle'], 'Publish the Pulse configuration and migration');
            $commands->addCommand('pulse:clear', [ClearCommand::class, 'handle'], 'Clear all recorded Spark Pulse entries');
            $commands->addCommand('pulse:prune', [PruneCommand::class, 'handle'], 'Prune old Spark Pulse entries');
            $commands->addCommand('pulse:check', [CheckCommand::class, 'handle'], 'Check Spark Pulse status and active watchers');
        }

        if (!config('pulse.enabled', true)) {
            return;
        }

        $router = $this->app->get(Router::class);
        $router->group([
            'path' => trim((string) config('pulse.path', 'pulse'), '/'),
            'name' => 'pulse',
            'middleware' => array_unique([...(array) config('pulse.middleware', []), AuthorizePulse::class, VerifyCsrfToken::class]),
        ], static function () {
            require __DIR__ . '/../routes/web.php';
        });

        $pulse = $this->app->get(Pulse::class);

        $this->listenForExecutions($pulse);

        foreach ($this->watchers as $watcherClass) {
            (new $watcherClass())->register($pulse);
        }
    }

    private function listenForExecutions(Pulse $pulse): void
    {
        Pulse::newBatchId();
        Telemetry::listen('command.starting', fn() => $pulse->beginExecution('command'), PHP_INT_MAX);
        Telemetry::listen('command.finished', fn() => $pulse->endExecution('command'), PHP_INT_MIN);

        $jobScope = static fn(array $payload): string => 'job:' . ($payload['id'] ?? '');
        Telemetry::listen('job.processing', fn(array $payload) => $pulse->beginExecution($jobScope($payload)), PHP_INT_MAX);
        // Spark can fail while resolving retry settings, before it emits job.processing.
        Telemetry::listen('job.failed', fn(array $payload) => $pulse->beginExecution($jobScope($payload), ifMissing: true), PHP_INT_MAX);
        $finishJob = static function (array $payload) use ($pulse, $jobScope): void {
            try {
                $pulse->flush();
            } finally {
                $pulse->endExecution($jobScope($payload));
            }
        };
        Telemetry::listen('job.processed', $finishJob, PHP_INT_MIN);
        Telemetry::listen('job.failed', $finishJob, PHP_INT_MIN);
    }
}
