# Spark Pulse

Pulse is a request and execution inspector for TinyMVC Spark. Open a request to see its duration, status, and payload, then follow its trace through database queries, logs, views, and other activity from the same execution. The Blade dashboard runs without a frontend build.

## Install

Requires PHP 8.2+ with `mbstring` and TinyCore 4.2.15+ in the 4.x series. Database storage requires the PDO driver for your database. From your Spark application, install the tagged release when available.

```sh
composer require --dev tinymvc/pulse
```

Add the provider to the array in `bootstrap/providers.php`.

```php
\Spark\Pulse\PulseServiceProvider::class,
```

Set `APP_ENV=local` in your development application's `.env`. Publish the configuration and migration, then create the database table.

```sh
php spark pulse:install
php spark migrate
```

Visit a route in your application, then open **`/pulse`**. Select an entry to inspect its payload and execution trace. Live updates refresh the overview and lists every five seconds.

Open **Language files** to see which translation files Spark loaded and how long each load took. Request details count the loads, link to the largest observed allocation increase, and show each file in the trace. Cached lookups produce no additional entry. Set `watchers.language_files.enabled` to `false` to disable this watcher.

## Reading the measurements

- **Duration** is Spark’s elapsed time in milliseconds. The dashboard displays short operations in microseconds or nanoseconds. A request spans dispatch through response preparation. A Blade duration covers running the compiled template and includes nested renders. Adding trace durations can double-count that work. The dashboard uses the original payload measurement when available.
- **PHP memory** is allocator-reserved memory. A request’s peak is the PHP process high-water mark and can include earlier work in a long-running worker. Queries, views, and language files report the signed allocation change from Spark’s pre-operation sample to Pulse’s completion listener. A zero change can mean PHP reused memory. Raw PHP allocation fields are in bytes. Display values use binary units such as MiB.
- **Server metrics** describe the machine and the filesystem containing application storage. CPU percentage needs two Linux counter readings in the same PHP process. Unsupported host metrics are unavailable. The overview samples its serving process, and the performance watcher records a fresh snapshot for a configurable percentage of requests (1% by default).
- **Repeated queries** count executions of the same SQL text on the same connection within a trace. Bound values can differ.
- **Time** is stored in UTC. With JavaScript enabled, the dashboard shows browser-local time on a 12-hour clock. Trace timestamps mark when Pulse observed completion.

## Configuration

Edit `config/pulse.php` to enable individual watchers, ignore paths or commands, and adjust slow-operation thresholds. The dashboard excludes its own requests. To customize its Blade templates, run `php spark pulse:install --views` and edit `resources/views/vendor/pulse`. The installer preserves existing files.

| Setting | Default | Purpose |
| --- | --- | --- |
| `PULSE_ENABLED` | `true` | Enable recording and dashboard routes |
| `PULSE_PATH` | `pulse` | Mount the dashboard and its API |
| `PULSE_STORAGE_DRIVER` | `database` | Persist entries across requests. Use `memory` for process-local tests |
| `PULSE_DB_CONNECTION` | App default | Select a configured database connection before migrating |

Pulse masks configured sensitive keys in structured watcher payloads. SQL bindings and mail bodies are opt-in through `watchers.queries.record_bindings` and `watchers.mail.record_body`. Free-text logs, raw SQL, and exception messages can contain application data.

### Watcher exclusions

In `config/pulse.php`, add `exclude` to any watcher. For example, to skip queued, processed, and failed entries for one job, set the following inside `watchers`.

```php
'jobs' => [
    'enabled' => true,
    'exclude' => [\App\Jobs\PublishRealtime::class],
],
```

Rules are case-sensitive and match a whole identifier. Use `*` for a wildcard, such as `'App\Jobs\Realtime\*'` for a namespace or `'session:*'` for cache keys. An empty list records all otherwise eligible entries.

| Watcher | What `exclude` matches |
| --- | --- |
| `jobs`, `models`, `exceptions` | Fully qualified class name. Jobs also support their queue display name |
| `requests`, `performance` | Incoming request path, with or without the leading slash |
| `commands`, `events`, `gates` | Command name, named event, or ability, respectively |
| `cache` | Cache key, including individual keys in a bulk forget |
| `http_client` | Host or URL without credentials, query string, or fragment |
| `logs` | Lowercase log level or message |
| `mail` | Subject or any To address, for sent and failed mail |
| `queries` | SQL statement or connection name |
| `views` | Template name shown in Pulse or compiled file path |
| `language_files` | File path shown in Pulse, such as `lang/en/orders.php` |

Each rule affects new entries from that watcher. An excluded job still runs, and its queries and logs follow their own rules. Use the top-level `ignore_paths` to silence all watchers during matching HTTP requests. Restart long-running queue workers after changing their configuration.

## Access

The dashboard and API allow access by default in the `local` and `testing` environments. To grant access elsewhere, register an authorization callback in your application provider's `boot()` method. This example uses a Spark ability defined by your application.

```php
use Spark\Pulse\Facades\Pulse;

Pulse::auth(fn () => can('view-pulse'));
```

## Maintenance

```sh
php spark pulse:check
php spark pulse:prune --hours=24
php spark pulse:clear
```

Schedule `pulse:prune` to enforce retention. It defaults to the 24-hour window in `storage.prune_hours`. `pulse:clear` deletes all recorded entries.

Run the package tests with `composer test` from the Pulse checkout.
