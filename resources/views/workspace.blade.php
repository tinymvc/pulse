@php
    $labels = array_merge(...array_values($groups));
    $title = $entry ? 'Entry details' : ($overview ? 'Overview' : $labels[$tab]);
    $appName = config('app.name') ?: 'Spark';
    $env = (string) $server['runtime']['app_env'];
    $envClass = in_array(strtolower($env), ['production', 'prod'], true) ? 'env-prod' : '';
@endphp
<div class="shell">
    <aside class="rail">
        <div class="rail-head">
            <a class="brand" href="{{ $path }}" aria-label="Spark Pulse overview">
                <span class="signal" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
                <span>Spark <strong>Pulse</strong></span>
            </a>
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
        <nav aria-label="Telemetry">
            <a class="nav-link overview-link" href="{{ $path }}"
                @if ($overview && !$entry) aria-current="page" @endif>
                <svg viewBox="0 0 16 16" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true">
                    <rect x="2" y="2" width="4.5" height="4.5" rx="1"/><rect x="9.5" y="2" width="4.5" height="4.5" rx="1"/>
                    <rect x="2" y="9.5" width="4.5" height="4.5" rx="1"/><rect x="9.5" y="9.5" width="4.5" height="4.5" rx="1"/>
                </svg> Overview
            </a>
            @foreach ($groups as $group => $items)
                <h2>{{ $group }}</h2>
                @foreach ($items as $type => $label)
                    <a class="nav-link" href="{{ $path }}?tab={{ $type }}"
                        @if ($tab === $type) aria-current="page" @endif>
                        <span>{{ $label }}</span>
                        <span class="nav-count"
                            data-live="count-{{ $type }}">{{ number_format($counts[$type] ?? 0) }}</span>
                    </a>
                @endforeach
            @endforeach
        </nav>
        <form class="mobile-nav" action="{{ $path }}" method="get">
            <select name="tab" aria-label="Activity type">
                <option value="dashboard" @selected($overview)>Overview</option>
                @foreach ($groups as $group => $items)
                    <optgroup label="{{ $group }}">
                        @foreach ($items as $type => $label)
                            <option value="{{ $type }}" @selected($tab === $type)>{{ $label }}
                            </option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
            <button type="submit">Go</button>
        </form>
    </aside>
    <main id="main" tabindex="-1">
        <header class="masthead">
            <div class="frame masthead-inner">
                <div class="app-context">
                    <svg viewBox="0 0 20 20" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.45" stroke-linejoin="round" aria-hidden="true">
                        <path d="m10 2 7.5 4.2v7.6L10 18l-7.5-4.2V6.2L10 2Z M2.5 6.2 10 10.5l7.5-4.3 M10 10.5V18"/>
                    </svg>
                    <span class="app-name">{{ $appName }}</span>
                    <span class="environment {{ $envClass }}"><span class="sr-only">Environment: </span>{{ $env }}</span>
                </div>
                <div class="heading-row">
                    <div>
                        <h1>{{ $title }}</h1>
                        @if ($entry)
                            <a class="back" href="{{ $path . '?' . http_build_query($filters) }}">← Back to {{ $overview ? 'overview' : $labels[$tab] }}</a>
                        @elseif ($overview)
                            <p>Application activity and runtime at a glance.</p>
                        @else
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
            </div>
        </header>
        <div class="content frame">
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
