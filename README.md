# Spark Pulse

Request and execution inspector for [TinyMVC Spark](https://github.com/tinymvc).

Open any request to see its duration, status, and payload, then follow its trace through database queries, logs, views, and other activity from the same execution.

## Requirements

- PHP 8.2 or later with the `mbstring` extension
- TinyCore 4.2.16 or later in the 4.x series
- The PDO driver for your database (database storage is the default)

## Installation

Install Pulse as a development dependency:

```sh
composer require --dev tinymvc/pulse
```

Register the provider in `bootstrap/providers.php`:

```php
\Spark\Pulse\PulseServiceProvider::class,
```

Set `APP_ENV=local` in your `.env`, then publish the configuration and migration and create the table:

```sh
php spark pulse:install
php spark migrate
```

Visit any route in your application, then open **`/pulse`**.

> **Deploying with `composer install --no-dev`?** The provider class will not exist in production. Register it conditionally:
>
> ```php
> ...(class_exists(\Spark\Pulse\PulseServiceProvider::class)
>     ? [\Spark\Pulse\PulseServiceProvider::class]
>     : []),
> ```

## Usage

Select an entry to inspect its payload and execution trace. The overview and lists refresh every five seconds.

- **Language files** shows which translation files Spark loaded and how long each load took. Request details count the loads, link to the largest observed allocation increase, and list each file in the trace. Cached lookups produce no additional entry.
- **Exceptions** shows the original message, file, line, and stack trace. Pulse also captures route exceptions that reach its middleware and are converted to a 5xx response by an outer middleware.

If a controller catches an exception internally, report it manually:

```php
\Spark\Telemetry::emit('exception.occurred', ['exception' => $e, 'handled' => true]);
```

## What Pulse records

Each watcher can be enabled or disabled individually in `config/pulse.php`.

| Watcher | Records |
| --- | --- |
| `requests` | Incoming requests: duration, status, payload |
| `performance` | Server and memory snapshots, sampled per request (1% by default) |
| `queries` | Database queries, including repeated-query counts per trace |
| `views` | Blade template renders, including nested renders |
| `language_files` | Loaded translation files, load time, and memory change |
| `exceptions` | Exceptions with message, file, line, and stack trace |
| `logs` | Application log entries |
| `cache` | Cache operations |
| `events` | Dispatched events |
| `gates` | Authorization ability checks |
| `models` | Model activity |
| `jobs` | Queued, processed, and failed jobs |
| `commands` | Console commands |
| `mail` | Sent and failed mail |
| `http_client` | Outgoing HTTP requests |

To disable a watcher, set its `enabled` option to `false`, for example `watchers.language_files.enabled`.

## Reading the measurements

- **Duration** is Spark's elapsed time in milliseconds. The dashboard displays short operations in microseconds or nanoseconds. A request spans dispatch through response preparation. A Blade duration covers running the compiled template and includes nested renders, so adding trace durations can double-count that work. The dashboard uses the original payload measurement when available.
- **PHP memory** is allocator-reserved memory. A request's peak is the PHP process high-water mark and can include earlier work in a long-running worker. Queries, views, and language files report the signed allocation change from Spark's pre-operation sample to Pulse's completion listener; a zero change can mean PHP reused memory. Raw allocation fields are in bytes, and display values use binary units such as MiB.
- **Server metrics** describe the machine and the filesystem containing application storage. CPU percentage needs two Linux counter readings in the same PHP process, and unsupported host metrics are shown as unavailable. The overview samples its serving process; the performance watcher records a fresh snapshot for a configurable percentage of requests.
- **Repeated queries** count executions of the same SQL text on the same connection within a trace. Bound values can differ.
- **Time** is stored in UTC. With JavaScript enabled, the dashboard shows browser-local time on a 12-hour clock. Trace timestamps mark when Pulse observed completion.

## Configuration

Edit `config/pulse.php` to enable individual watchers, ignore paths or commands, and adjust slow-operation thresholds. The dashboard excludes its own requests.

| Setting | Default | Purpose |
| --- | --- | --- |
| `PULSE_ENABLED` | `true` | Enable recording and dashboard routes |
| `PULSE_PATH` | `pulse` | Path where the dashboard and its API are mounted |
| `PULSE_STORAGE_DRIVER` | `database` | Persist entries across requests. Use `memory` for process-local tests |
| `PULSE_DB_CONNECTION` | App default | Database connection to use. Set it before migrating |

`pulse:install` publishes the `pulse-config` and `pulse-migrations` tags. Add `--views` to include the dashboard templates, or publish any tag individually with `vendor:publish`. Existing files are preserved unless you pass `--force`.

To customize the dashboard, publish the views and edit them in `resources/views/vendor/pulse`:

```sh
php spark vendor:publish --tag=pulse-views
```

### Watcher exclusions

Add an `exclude` list to any watcher in `config/pulse.php`. For example, to skip queued, processed, and failed entries for one job:

```php
'jobs' => [
    'enabled' => true,
    'exclude' => [\App\Jobs\PublishRealtime::class],
],
```

Rules are case-sensitive and match a whole identifier. Use `*` as a wildcard, such as `'App\Jobs\Realtime\*'` for a namespace or `'session:*'` for cache keys. An empty list records all otherwise eligible entries.

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

Each rule affects only new entries from that watcher. An excluded job still runs, and its queries and logs follow their own rules. To silence every watcher during matching HTTP requests, use the top-level `ignore_paths` setting. Restart long-running queue workers after changing configuration.

## Access and security

The dashboard and API are open by default in the `local` and `testing` environments. To grant access elsewhere, register an authorization callback in your application provider's `boot()` method. This example uses an ability your application defines:

```php
use Spark\Pulse\Facades\Pulse;

Pulse::auth(fn () => can('view-pulse'));
```

Before enabling Pulse outside local development, note what it stores:

- Configured sensitive keys are masked in structured watcher payloads.
- SQL bindings and mail bodies are opt-in (`watchers.queries.record_bindings`, `watchers.mail.record_body`).
- Free-text logs, raw SQL, and exception messages can contain application data.

## Maintenance

```sh
php spark pulse:check
php spark pulse:prune --hours=24
php spark pulse:clear
```

| Command | Purpose |
| --- | --- |
| `pulse:check` | Verify the Pulse installation |
| `pulse:prune` | Delete entries older than the retention window (default 24 hours, set by `storage.prune_hours`) |
| `pulse:clear` | Delete all recorded entries |

Schedule `pulse:prune` to enforce retention.

## Troubleshooting

If `/pulse` is empty or unavailable:

- Confirm `PULSE_ENABLED` is `true` and the migration has run.
- Confirm `APP_ENV=local`, or that your `Pulse::auth()` callback allows you.
- Check that the request path is not listed in `ignore_paths`.

## Testing

Run the package tests from the Pulse checkout:

```sh
composer test
```