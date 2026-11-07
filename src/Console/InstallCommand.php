<?php

namespace Spark\Pulse\Console;

use FilesystemIterator;
use RuntimeException;
use Spark\Console\Prompt;
use function array_key_exists;

class InstallCommand
{
    public function handle(array $args = []): int
    {
        Prompt::section('Installing Spark Pulse');

        $package = dirname(__DIR__, 2);

        try {
            $this->publish("$package/config/pulse.php", root_dir('config/pulse.php'));
            $this->publish("$package/database/migrations", root_dir('database/migrations'));
            if (array_key_exists('views', $args)) {
                $this->publish("$package/resources/views", views_dir('vendor/pulse'));
            }
        } catch (RuntimeException $error) {
            Prompt::message($error->getMessage(), 'danger');
            return 1;
        }

        Prompt::message('Pulse files successfully installed.', 'success');
        Prompt::message('For database storage, run php spark migrate.', 'info');
        return 0;
    }

    private function publish(string $source, string $destination): void
    {
        if (is_dir($source)) {
            foreach (new FilesystemIterator($source) as $file) {
                $this->publish($file->getPathname(), "$destination/" . $file->getFilename());
            }
            return;
        }

        if (is_file($destination)) {
            Prompt::message("Keeping $destination", 'info');
            return;
        }

        $directory = dirname($destination);
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException("Could not create $directory");
        }
        if (!copy($source, $destination)) {
            throw new RuntimeException("Could not publish $destination");
        }
        Prompt::message("Published $destination", 'success');
    }
}
