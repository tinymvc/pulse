@php
    $labels = array_merge(...array_values($groups));
    $title = $entry ? 'Entry details' : ($overview ? 'Overview' : $labels[$tab]);
    $env = (string) $server['runtime']['app_env'];
    $envClass = in_array(strtolower($env), ['production', 'prod'], true) ? 'env-prod' : '';
@endphp
<div class="shell">
    <aside class="rail">
        <a class="brand" href="{{ $path }}" aria-label="Spark Pulse overview">
            <span class="signal" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
            Spark <strong>Pulse</strong>
        </a>
        <nav aria-label="Telemetry">
            <a class="nav-link overview-link" href="{{ $path }}"
                @if ($overview && !$entry) aria-current="page" @endif>
                <span class="grid-icon" aria-hidden="true"></span> Overview
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
    <main id="main">
        <header class="topbar">
            <span class="app-name"><span class="app-icon"
                    aria-hidden="true">⌘</span>{{ config('app.name', 'Spark') }}</span>
            <span class="crumbs">
                <span class="separator" aria-hidden="true">/</span>
                <span>Observability</span>
                <span class="separator" aria-hidden="true">/</span>
                <span class="crumb-current">{{ $title }}</span>
            </span>
            <span class="environment {{ $envClass }}">{{ $env }}</span>
            <button type="button" class="icon-button" id="theme" aria-label="Toggle dark mode"
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
        </header>
        <div class="content">
            <p class="paused" data-live="recording" @if ($recording) hidden @endif>Recording is paused.
                Previously recorded entries are available below.</p>
            <div class="page-head">
                <div>
                    @if ($entry)
                        <a class="back" href="{{ $path . '?' . http_build_query($filters) }}">← Back to
                            {{ $overview ? 'overview' : $labels[$tab] }}</a>
                    @endif
                    <h1>{{ $title }}</h1>
                </div>
                @if (!$entry)
                    <div class="actions" hidden data-tools>
                        <button type="button" class="control live" id="refresh" aria-pressed="true"><span
                                class="dot"></span><span id="refresh-label">Live updates</span></button>
                        <button type="button" class="control danger" id="clear"
                            data-url="{{ route_url('pulse.api.clear') }}">Clear entries</button>
                    </div>
                @endif
            </div>

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
