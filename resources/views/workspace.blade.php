@php
    $labels = array_merge(...array_values($groups));
    $title = $entry ? 'Entry details' : ($overview ? 'Overview' : $labels[$tab]);
    $activeTab = $entry['type'] ?? $tab;
    $navIcons = [
        'request' => 'M2 5h11m-3-3 3 3-3 3M14 11H3m3-3-3 3 3 3',
        'query' => 'M13 4c0 2.7-10 2.7-10 0s10-2.7 10 0ZM3 4v8c0 2.7 10 2.7 10 0V4M3 8c0 2.7 10 2.7 10 0',
        'exception' => 'm8 2 6 11H2L8 2Zm0 4v3m0 2v.2',
        'log' => 'M3 2.5h10v11H3zM5.5 5.5h5m-5 2.5h5m-5 2.5h3',
        'view' => 'M1.5 8s2.5-4.5 6.5-4.5 6.5 4.5 6.5 4.5-2.5 4.5-6.5 4.5S1.5 8 1.5 8ZM10 8a2 2 0 1 1-4 0 2 2 0 1 1 4 0Z',
        'language_file' => 'M2 4h8M6 2v2m2.5 0c-.7 3.1-2.7 5.7-6 7M4 6c.8 1.8 2.2 3.2 4 4m1 4 3-7 3 7m-5-2h4',
        'model' => 'm8 1.8 5.5 3.1v6.2L8 14.2l-5.5-3.1V4.9L8 1.8Zm0 6.4 5.5-3.3M8 8.2 2.5 4.9M8 8.2v6',
        'job' => 'M2 5h12v8H2zM5.5 5V3h5v2M2 8.5h12m-7 0v1h2v-1',
        'command' => 'm3 4 4 4-4 4m5.5 0h4.5',
        'event' => 'M9 1.5 3.5 9H7l-.5 5.5L13 6.8H9l0-5.3Z',
        'gate' => 'M8 1.7 13 4v4c0 3-2.5 5-5 6.3C5.5 13 3 11 3 8V4l5-2.3Zm-2.3 6.5L7.3 10l3-3.7',
        'cache' => 'm8 2 6 3.4-6 3.4-6-3.4L8 2ZM2 8.3l6 3.4 6-3.4M2 11.2l6 3.3 6-3.3',
        'http_client' => 'M14 8A6 6 0 1 1 2 8a6 6 0 1 1 12 0ZM2 8h12m-6-6c3 3.4 3 8.6 0 12-3-3.4-3-8.6 0-12Z',
        'mail' => 'M2 3.5h12v9H2zM2 4l6 4.5L14 4',
        'performance' => 'M1 9h3l2.5-6 3 10 2-5H15',
    ];
    $appName = config('app.name') ?: 'Spark';
    $env = (string) $server['runtime']['app_env'];
    $envClass = in_array(strtolower($env), ['production', 'prod'], true) ? 'env-prod' : '';
@endphp
<div class="shell">
    <header class="workspace-bar">
        <div class="brand-cell">
            <button class="icon-button nav-toggle" type="button" popovertarget="telemetry-nav"
                aria-label="Navigation" title="Navigation" hidden>
                <svg viewBox="0 0 16 16" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true">
                    <path d="M2 4h12M2 8h12M2 12h12"/>
                </svg>
            </button>
            <a class="brand" href="{{ $path }}" aria-label="Spark Pulse overview">
                <span class="signal" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
                <span><span class="brand-prefix">Spark </span><strong>Pulse</strong></span>
            </a>
        </div>
        <div class="bar-body">
            <div class="app-context">
                <svg viewBox="0 0 20 20" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.45" stroke-linejoin="round" aria-hidden="true">
                    <path d="m10 2 7.5 4.2v7.6L10 18l-7.5-4.2V6.2L10 2Z M2.5 6.2 10 10.5l7.5-4.3 M10 10.5V18"/>
                </svg>
                <span class="app-name" title="{{ $appName }}">{{ $appName }}</span>
                <span class="environment {{ $envClass }}" title="{{ $env }}"><span class="sr-only">Environment: </span>{{ $env }}</span>
            </div>
            <button type="button" class="icon-button" id="theme" aria-label="Dark mode"
                title="Toggle dark mode">
                <svg class="icon-moon" viewBox="0 0 16 16" width="16" height="16" aria-hidden="true">
                    <path d="M13.5 9.5A5.5 5.5 0 0 1 6.5 2.5a5.5 5.5 0 1 0 7 7Z" fill="none" stroke="currentColor"
                        stroke-width="1.5" stroke-linejoin="round" />
                </svg>
                <svg class="icon-sun" viewBox="0 0 16 16" width="16" height="16" aria-hidden="true">
                    <circle cx="8" cy="8" r="3" fill="none" stroke="currentColor"
                        stroke-width="1.5" />
                    <path
                        d="M8 1.5V3M8 13v1.5M1.5 8H3M13 8h1.5M3.4 3.4l1.1 1.1M11.5 11.5l1.1 1.1M3.4 12.6l1.1-1.1M11.5 4.5l1.1-1.1"
                        stroke="currentColor" stroke-width="1.5" stroke-linecap="round" />
                </svg>
            </button>
        </div>
    </header>
    <aside class="rail" id="telemetry-nav">
        <nav aria-label="Telemetry">
            <a class="nav-link overview-link" href="{{ $path }}"
                @if ($overview && !$entry) aria-current="page" @endif>
                <svg class="nav-glyph" viewBox="0 0 16 16" aria-hidden="true">
                    <rect x="2" y="2" width="4.5" height="4.5" rx="1"/><rect x="9.5" y="2" width="4.5" height="4.5" rx="1"/>
                    <rect x="2" y="9.5" width="4.5" height="4.5" rx="1"/><rect x="9.5" y="9.5" width="4.5" height="4.5" rx="1"/>
                </svg> Overview
            </a>
            @foreach ($groups as $group => $items)
                <h2>{{ $group }}</h2>
                @foreach ($items as $type => $label)
                    <a class="nav-link" href="{{ $path }}?tab={{ $type }}"
                        @if ($activeTab === $type) aria-current="{{ $entry ? 'location' : 'page' }}" @endif>
                        <svg class="nav-glyph" viewBox="0 0 16 16" aria-hidden="true">
                            <path d="{{ $navIcons[$type] ?? 'M3 3h10v10H3z' }}"/>
                        </svg>
                        <span>{{ $label }}</span>
                        <span class="nav-count"
                            data-live="count-{{ $type }}">{{ number_format($counts[$type] ?? 0) }}</span>
                    </a>
                @endforeach
            @endforeach
        </nav>
    </aside>
    <main class="content" id="main" tabindex="-1">
        <div class="frame">
            <div class="heading-row">
                <div>
                    <h1>{{ $title }}</h1>
                    @if ($entry)
                        <a class="back" href="{{ $path . '?' . http_build_query($filters) }}">← Back to {{ $overview ? 'overview' : $labels[$tab] }}</a>
                    @elseif (!$overview)
                        <p data-live="tab-total">{{ number_format($counts[$tab] ?? 0) }} recorded {{ ($counts[$tab] ?? 0) === 1 ? 'entry' : 'entries' }}</p>
                    @endif
                </div>
                @if (!$entry)
                    <div class="actions" hidden data-tools>
                        <button type="button" class="control live" id="refresh" aria-pressed="true">
                            <span class="dot" aria-hidden="true"></span><span id="refresh-label">Live updates</span>
                        </button>
                        <button type="button" class="control danger" id="clear" data-url="{{ route_url('pulse.api.clear') }}">
                            <svg viewBox="0 0 16 16" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M2.5 4.5h11M6 4.5V3h4v1.5M4 4.5l.7 8a1 1 0 0 0 1 .9h4.6a1 1 0 0 0 1-.9l.7-8"/>
                            </svg> Clear all entries
                        </button>
                    </div>
                @endif
            </div>
            <p class="paused" data-live="recording" @if ($recording) hidden @endif>Recording is paused.
                Previously recorded entries are available below.</p>
            @if ($entry)
                <x-pulse::detail :$entry :$trace :$languageLoads :$labels :$path :$filters />
            @else
                @if ($overview)
                    <x-pulse::overview :$counts :$server :$path />
                @else
                    <form class="filter" method="get" action="{{ $path }}">
                        <input type="hidden" name="tab" value="{{ $tab }}">
                        <label for="search">Search {{ strtolower($labels[$tab]) }}</label>
                        <div class="search-box">
                            <div class="search-field">
                                <svg class="search-icon" viewBox="0 0 16 16" width="14" height="14"
                                    aria-hidden="true">
                                    <circle cx="7" cy="7" r="4.5" fill="none" stroke="currentColor"
                                        stroke-width="1.5" />
                                    <path d="m10.5 10.5 3 3" stroke="currentColor" stroke-width="1.5"
                                        stroke-linecap="round" />
                                </svg>
                                <input type="search" name="search" id="search" value="{{ $search }}"
                                    placeholder="Search recorded content">
                            </div>
                            <button class="control" type="submit">Search</button>
                            @if ($search !== '')
                                <a href="{{ $path }}?tab={{ $tab }}">Reset</a>
                            @endif
                        </div>
                    </form>
                @endif
                <x-pulse::entries :$tab :$page :$overview :$path :$filters :$search />
            @endif
        </div>
    </main>
</div>
