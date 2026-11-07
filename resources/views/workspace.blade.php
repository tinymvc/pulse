@php
    $labels = array_merge(...array_values($groups));
    $title = $entry ? 'Entry details' : ($overview ? 'Overview' : $labels[$tab]);
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
                        <span>{{ $label }}</span><span class="nav-count"
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
            <span class="separator">/</span><span>Observability</span>
            <span class="environment">{{ $server['runtime']['app_env'] }}</span>
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
                        <button type="button" class="control" id="clear"
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
                            <input type="search" name="search" id="search" value="{{ $search }}"
                                placeholder="Search recorded content">
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
