<?php

namespace Tests\Feature;

use Spark\Console\Commands;
use Spark\Console\Console;
use Spark\Facades\App;
use Spark\Pulse\Entry;
use Spark\Pulse\EntryType;
use Spark\Pulse\Pulse;
use Tests\TestCase;

class ConsoleCommandsTest extends TestCase
{
    public function test_pulse_install_command_runs(): void
    {
        /** @var Console $console */
        $console = App::get(Console::class);

        ob_start();
        $console->executeCommand('pulse:install', ['views' => true]);
        $output = ob_get_clean();

        $this->assertStringContainsString('Installing Spark Pulse', $output);
        $this->assertStringContainsString('successfully installed', $output);
        $this->assertFileExists($this->storagePath . '/config/pulse.php');
        $this->assertFileExists(views_dir('vendor/pulse/components/detail.blade.php'));
        $view = views_dir('vendor/pulse/dashboard.blade.php');
        file_put_contents($view, 'Published dashboard');
        ob_start();
        $console->executeCommand('pulse:install', ['views' => true]);
        ob_end_clean();
        $this->get('/pulse')->assertSee('Published dashboard');
    }

    public function test_pulse_check_command_outputs_diagnostics(): void
    {
        $pulse = Pulse::instance();
        $pulse->record(Entry::make(EntryType::COMMAND, ['command' => 'test']));
        $pulse->flush();

        /** @var Console $console */
        $console = App::get(Console::class);

        ob_start();
        $console->executeCommand('pulse:check', []);
        $output = ob_get_clean();

        $this->assertStringContainsString('Spark Pulse Health & Diagnostics', $output);
        $this->assertStringContainsString('Status: ACTIVE', $output);
        $this->assertStringContainsString('Command', $output);
        $this->assertStringContainsString('Server Quick Metrics', $output);
    }

    public function test_pulse_clear_and_prune_commands(): void
    {
        $pulse = Pulse::instance();
        $pulse->record(Entry::make(EntryType::LOG, ['message' => 'sample']));
        $pulse->flush();

        $this->assertSame(1, $pulse->storage()->count(EntryType::LOG));

        /** @var Console $console */
        $console = App::get(Console::class);

        // Test prune
        ob_start();
        $console->executeCommand('pulse:prune', ['hours' => 24]);
        $output = ob_get_clean();
        $this->assertStringContainsString('Pruned', $output);

        // Test clear
        ob_start();
        $console->executeCommand('pulse:clear', []);
        $output = ob_get_clean();
        $this->assertStringContainsString('cleared', $output);

        $this->assertSame(0, $pulse->storage()->count(EntryType::LOG));
    }
}
