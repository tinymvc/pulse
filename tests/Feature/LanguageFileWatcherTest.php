<?php

namespace Tests\Feature;

use Spark\Http\Routing\Router;
use Spark\Pulse\Entry;
use Spark\Pulse\EntryType;
use Spark\Pulse\Pulse;
use Spark\Pulse\Recorders\ServerPerformanceRecorder;
use Spark\Pulse\Watchers\LanguageFileWatcher;
use Tests\TestCase;

class LanguageFileWatcherTest extends TestCase
{
    public function test_language_file_loads_appear_in_the_request_trace_and_dashboard(): void
    {
        $dir = $this->storagePath . '/lang';
        mkdir("$dir/en", 0755, true);
        file_put_contents("$dir/fr.php", "<?php return ['hello' => 'Bonjour'];");
        file_put_contents("$dir/en/orders.php", "<?php return ['title' => 'Order details'];");
        file_put_contents("$dir/extra.php", "<?php return ['extra' => 'Additional translation'];");

        $translator = trans()->addPath($dir);
        $translator->setLocale('fr_CA');
        $translator->setFallback('en');
        $translator->addLanguageFile("$dir/extra.php", locale: 'fr');
        $this->app->get(Router::class)->get('/order', function () {
            return json([trans('hello'), trans('orders.title'), trans('orders.title'), trans('extra')]);
        });

        $this->assertSame(['Bonjour', 'Order details', 'Order details', 'Additional translation'], $this->get('/order')->assertOk()->json());
        $store = Pulse::instance()->storage();
        $request = $store->get('request')['entries'][0];
        $entries = $this->get('/pulse/api/entries?type=language_files')->assertOk()->json()['entries'];
        $this->assertCount(3, $entries);
        $paths = [];
        foreach ($entries as $entry) {
            $content = $entry['content'];
            $paths[] = $content['path'];
            $this->assertSame($request['batch_id'], $entry['batch_id']);
            $this->assertGreaterThanOrEqual(0, $content['duration_ms']);
            $this->assertSame($content['allocated_after_bytes'] - $content['allocated_before_bytes'], $content['allocated_change_bytes']);
        }
        sort($paths);
        $this->assertSame(['lang/en/orders.php', 'lang/extra.php', 'lang/fr.php'], $paths);
        $this->assertFalse(str_contains(json_encode($entries), 'Additional translation'));

        $this->get('/pulse?tab=language_files&search=orders')->assertOk()
            ->assertSee('Language files')->assertSee('PHP allocation change')->assertSee('Load time')->assertSee('lang/en/orders.php');
        $this->get('/pulse?entry=' . $request['id'])->assertOk()
            ->assertSee('Language file loads')
            ->assertSee('lang/en/orders.php')->assertSee('PHP allocation ');
        $this->assertSame(3, $store->counts()[EntryType::LANGUAGE_FILE]);
    }

    public function test_rebuilding_a_group_reuses_files_and_flushing_reloads_them(): void
    {
        $file = $this->storagePath . '/strings.php';
        file_put_contents($file, "<?php return ['key' => 'first'];");
        $translator = trans();
        $translator->addLanguageFile($file);
        $this->assertSame('first', $translator->get('key'));
        $translator->addLines(['key' => 'override']);
        $this->assertSame('override', $translator->get('key'));
        $translator->flush();
        $this->assertSame('first', $translator->get('key'));

        $pulse = Pulse::instance();
        $pulse->stopRecording();
        $translator->flush();
        $this->assertSame('first', $translator->get('key'));
        $pulse->flush();
        $this->assertSame(2, $pulse->storage()->count(EntryType::LANGUAGE_FILE));
        $this->app->setConfig('pulse.watchers.language_files.enabled', false);
        $this->assertFalse((new LanguageFileWatcher())->isEnabled());
    }

    public function test_request_highlights_the_largest_positive_change_in_its_trace(): void
    {
        $pulse = Pulse::instance();
        $request = Entry::make(EntryType::REQUEST);
        $pulse->record($request);
        $file = fn(string $name, int $change) => Entry::make(EntryType::LANGUAGE_FILE, [
            'path' => $name, 'allocated_change_bytes' => $change, 'duration_ms' => 0.123,
        ], $request->batchId);
        $pulse->record($file('en/small.php', 0));
        $pulse->flush();
        $url = '/pulse?tab=request&entry=' . $request->id;
        $this->assertFalse(str_contains($this->get($url)->assertOk()->content(), 'Largest language file allocation increase'));

        $largest = $file('en/catalogue.php', 2097152);
        $pulse->record($largest);
        $pulse->record($file('en/other.php', 1024));
        $pulse->flush();
        $this->get($url)->assertOk()->assertSee('Largest language file allocation increase')
            ->assertSee('+2 MiB · en/catalogue.php')->assertSee('entry=' . $largest->id)->assertSee('123 µs');
    }

    public function test_allocation_change_preserves_zero_and_direction(): void
    {
        $this->assertSame('0 B', ServerPerformanceRecorder::formatByteChange(0));
        $this->assertSame('+2 MiB', ServerPerformanceRecorder::formatByteChange(2097152));
        $this->assertSame('−1 KiB', ServerPerformanceRecorder::formatByteChange(-1024));
    }
}
