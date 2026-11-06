<?php

namespace Spark\Pulse\Console;

use Spark\Console\Prompt;
use Spark\Foundation\Application;
use Spark\Pulse\Pulse;

/**
 * Spark Pulse Installation Command.
 *
 * Copies default configuration and creates necessary database tables.
 *
 * @package Spark\Pulse\Console
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class InstallCommand
{
    /**
     * Execute the command.
     *
     * @param array $args
     * @return int
     */
    public function handle(array $args = []): int
    {
        Prompt::section('Installing Spark Pulse');

        $app = Application::$app;
        $rootPath = $app !== null ? $app->getPath() : getcwd();

        // 1. Publish configuration file
        $configDest = dir_path("{$rootPath}/config/pulse.php");
        $configSrc = dir_path(__DIR__ . '/../../config/pulse.php');

        if (!file_exists($configDest)) {
            if (!is_dir(dirname($configDest))) {
                @mkdir(dirname($configDest), 0755, true);
            }
            if (copy($configSrc, $configDest)) {
                Prompt::message('Configuration file published to config/pulse.php', 'success');
            } else {
                Prompt::message('Could not copy configuration file to config/pulse.php', 'warning');
            }
        } else {
            Prompt::message('Configuration file already exists at config/pulse.php', 'info');
        }

        // 2. Ensure Database Tables Exist
        try {
            Pulse::instance()->storage()->ensureInstalled();
            Prompt::message('Pulse storage tables initialized successfully.', 'success');
        } catch (\Throwable $e) {
            Prompt::message('Failed to initialize database tables: ' . $e->getMessage(), 'danger');
            return 1;
        }

        // 3. Optionally publish views
        if (!empty($args['views'])) {
            $viewsDest = dir_path("{$rootPath}/resources/views/vendor/pulse");
            $viewsSrc = dir_path(__DIR__ . '/../../resources/views');
            if (is_dir($viewsSrc)) {
                $this->copyDirectory($viewsSrc, $viewsDest);
                Prompt::message('Blade views published to resources/views/vendor/pulse', 'success');
            }
        }

        Prompt::newline();
        Prompt::message('Spark Pulse has been successfully installed!', 'success');
        Prompt::message('Access your dashboard at: ' . url(config('pulse.path', 'pulse')), 'info');
        Prompt::newline();

        return 0;
    }

    /**
     * Recursively copy a directory.
     */
    private function copyDirectory(string $source, string $destination): void
    {
        if (!is_dir($destination)) {
            @mkdir($destination, 0755, true);
        }

        $items = scandir($source);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $srcPath = "{$source}/{$item}";
            $destPath = "{$destination}/{$item}";

            if (is_dir($srcPath)) {
                $this->copyDirectory($srcPath, $destPath);
            } else {
                copy($srcPath, $destPath);
            }
        }
    }
}
