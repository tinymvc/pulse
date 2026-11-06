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
        $this->copyFile(
            dir_path(__DIR__ . '/../../config/pulse.php'),
            dir_path("$rootPath/config/pulse.php"),
            'Configuration file published to config/pulse.php',
            'Configuration file could not be copied to config/pulse.php or already exists.'
        );

        // 2. Publish migration file
        $this->copyFile(
            dir_path(__DIR__ . '/../../database/migrations/migration_2026_10_30_000000_pulse_entries.php'),
            dir_path("$rootPath/database/migrations/migration_2026_10_30_000000_pulse_entries.php"),
            'Migration file published to database/migrations/migration_2026_10_30_000000_pulse_entries.php',
            'Migration file could not be copied to database/migrations/migration_2026_10_30_000000_pulse_entries.php or already exists.'
        );

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

            $srcPath = "$source/$item";
            $destPath = "$destination/$item}";

            if (is_dir($srcPath)) {
                $this->copyDirectory($srcPath, $destPath);
            } else {
                copy($srcPath, $destPath);
            }
        }
    }

    private function copyFile(
        string $source,
        string $destination,
        string $message,
        string $existsMessage
    ): void {
        if (!file_exists($destination)) {
            if (!is_dir(dirname($destination))) {
                @mkdir(dirname($destination), 0755, true);
            }
            if (copy($source, $destination)) {
                Prompt::message($message, 'success');
            }
        }

        Prompt::message($existsMessage, 'warning');
    }
}
