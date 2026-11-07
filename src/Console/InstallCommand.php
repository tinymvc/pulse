<?php

namespace Spark\Pulse\Console;

use RuntimeException;
use Spark\Console\Prompt;
use Spark\Foundation\Console\VendorPublisher;
use Spark\Pulse\PulseServiceProvider;
use function array_key_exists;

class InstallCommand
{
    public function handle(array $args = []): int
    {
        Prompt::section('Installing Spark Pulse');

        $publisher = app(VendorPublisher::class);
        $tags = ['pulse-config', 'pulse-migrations'];
        if (array_key_exists('views', $args)) {
            $tags[] = 'pulse-views';
        }

        try {
            foreach ($tags as $tag) {
                $publisher->publish(provider: PulseServiceProvider::class, tag: $tag);
            }
        } catch (RuntimeException $error) {
            Prompt::error($error->getMessage());
            return 1;
        }

        Prompt::message('Pulse files successfully installed.', 'success');
        Prompt::message('For database storage, run php spark migrate.', 'info');
        return 0;
    }
}
