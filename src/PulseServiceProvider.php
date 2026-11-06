<?php

namespace Spark\Pulse;

use Spark\Console\Commands;
use Spark\Foundation\Providers\ServiceProvider;
use Spark\Http\Middleware;
use Spark\Http\Routing\Router;
use Spark\Pulse\Console\CheckCommand;
use Spark\Pulse\Console\ClearCommand;
use Spark\Pulse\Console\InstallCommand;
use Spark\Pulse\Console\PruneCommand;
use Spark\Pulse\Watchers\CacheWatcher;
use Spark\Pulse\Watchers\CommandWatcher;
use Spark\Pulse\Watchers\EventWatcher;
use Spark\Pulse\Watchers\ExceptionWatcher;
use Spark\Pulse\Watchers\GateWatcher;
use Spark\Pulse\Watchers\HttpClientWatcher;
use Spark\Pulse\Watchers\JobWatcher;
use Spark\Pulse\Watchers\LogWatcher;
use Spark\Pulse\Watchers\MailWatcher;
use Spark\Pulse\Watchers\ModelWatcher;
use Spark\Pulse\Watchers\PerformanceWatcher;
use Spark\Pulse\Watchers\QueryWatcher;
use Spark\Pulse\Watchers\RequestWatcher;
use Spark\Pulse\Watchers\ViewWatcher;
use Spark\View\Blade;
use function is_string;

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
    /** @var \WeakMap|null Track booted routers to prevent duplicate route registrations */
    private static ?\WeakMap $bootedRouters = null;

    /** @var array<class-string> All 14 Telemetry Watchers */
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
        PerformanceWatcher::class,
    ];

    /**
     * {@inheritdoc}
     */
    public function register(): void
    {
        $this->app->singleton(Pulse::class, fn() => new Pulse());

        $this->app->alias('pulse', Pulse::class);
    }

    /**
     * {@inheritdoc}
     */
    public function boot(): void
    {
        $defaultConfig = require __DIR__ . '/../config/pulse.php';
        $appConfig = (array) $this->app->getConfig('pulse', []);
        $this->app->setConfig('pulse', array_replace_recursive($defaultConfig, $appConfig));

        if (!config('pulse.enabled', true)) {
            return;
        }

        $pulse = $this->app->get(Pulse::class);

        // 1. Register View Namespace
        if ($this->app->bound(Blade::class)) {
            try {
                $blade = $this->app->get(Blade::class);
                $viewsPath = dir_path(__DIR__ . '/../resources/views');
                if (is_dir($viewsPath)) {
                    $blade->usePath($viewsPath, 'pulse');
                }
            } catch (\Throwable) {
                // Ignore if views directory is not configured in current environment
            }
        }

        // 2. Register Dashboard Routes
        if ($this->app->bound(Router::class)) {
            self::$bootedRouters ??= new \WeakMap();
            $router = $this->app->get(Router::class);

            if (!isset(self::$bootedRouters[$router])) {
                self::$bootedRouters[$router] = true;
                $path = trim((string) config('pulse.path', 'pulse'), '/');
                $configured = (array) config('pulse.middleware', [\Spark\Pulse\Http\Middleware\AuthorizePulse::class]);
                $middleware = [];
                $middlewareManager = $this->app->bound(Middleware::class) ? $this->app->get(Middleware::class) : null;

                foreach ($configured as $item) {
                    if (is_callable($item) || (is_string($item) && class_exists($item))) {
                        $middleware[] = $item;
                    } elseif ($middlewareManager !== null && is_string($item) && $middlewareManager->has($item)) {
                        $middleware[] = $item;
                    }
                }

                $router->group([
                    'prefix' => $path,
                    'middleware' => $middleware,
                ], static function () {
                    require __DIR__ . '/../routes/web.php';
                });
            }
        }

        // 3. Register CLI Commands
        if ($this->app->bound(Commands::class)) {
            /** @var Commands $commands */
            $commands = $this->app->get(Commands::class);
            $commands->addCommand('pulse:install', [InstallCommand::class, 'handle'], 'Install Spark Pulse & create database tables');
            $commands->addCommand('pulse:clear', [ClearCommand::class, 'handle'], 'Clear all recorded Spark Pulse entries');
            $commands->addCommand('pulse:prune', [PruneCommand::class, 'handle'], 'Prune old Spark Pulse entries');
            $commands->addCommand('pulse:check', [CheckCommand::class, 'handle'], 'Check Spark Pulse status and active watchers');
        }

        // 4. Register All 14 Watchers
        foreach ($this->watchers as $watcherClass) {
            (new $watcherClass())->register($pulse);
        }
    }
}
