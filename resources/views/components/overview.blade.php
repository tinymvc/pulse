@props(['counts', 'server', 'path'])
@php
    $total = array_sum($counts);
    $distribution = [
        'Requests' => $counts['request'],
        'Queries' => $counts['query'],
        'Logs' => $counts['log'],
        'Other' => $total - $counts['request'] - $counts['query'] - $counts['log'],
    ];
    $disk = $server['disk']['usage_percent'] ?? null;
    $diskLevel = $disk === null ? '' : ($disk >= 95 ? 'crit' : ($disk >= 85 ? 'warn' : ''));
@endphp
<section class="summary-grid" aria-label="Telemetry summary" data-live="summary">
    <div class="activity panel">
        <h2>Recorded activity</h2>
        <div class="activity-body">
            <div class="dial">
                <svg viewBox="0 0 100 100" aria-hidden="true" fill="none" stroke-width="6">
                    <circle class="dial-track" cx="50" cy="50" r="46" />
                    @php($offset = 0)
                    @foreach ($distribution as $label => $count)
                        @if ($count > 0)
                            @php($share = (100 * $count) / $total)
                            <circle class="series-{{ strtolower($label) }}" cx="50" cy="50" r="46"
                                pathLength="100" stroke="currentColor" stroke-dasharray="{{ $share }} 100"
                                stroke-dashoffset="{{ -$offset }}" />
                            @php($offset += $share)
                        @endif
                    @endforeach
                </svg>
                <div class="dial-total">
                    <strong style="--digits: {{ strlen(number_format($total)) }}">{{ number_format($total) }}</strong>
                    <span>{{ $total === 1 ? 'entry' : 'entries' }}</span>
                </div>
            </div>
            <dl class="legend">
                @foreach ($distribution as $label => $count)
                    <div>
                        <dt><i class="series-{{ strtolower($label) }}" aria-hidden="true"></i>{{ $label }}</dt>
                        <dd>{{ number_format($count) }}<small>{{ $total ? round((100 * $count) / $total) : 0 }}%</small>
                        </dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </div>
    <div class="panel runtime">
        <h2>Runtime</h2>
        <dl class="facts">
            <div>
                <dt>Framework</dt>
                <dd>Spark {{ $server['runtime']['spark_version'] }}</dd>
            </div>
            <div>
                <dt>PHP</dt>
                <dd>{{ $server['runtime']['php_version'] }}</dd>
            </div>
            <div>
                <dt>Operating system</dt>
                <dd>{{ PHP_OS_FAMILY }}</dd>
            </div>
        </dl>
    </div>
</section>
<section class="metrics" aria-label="Server metrics" data-live="metrics">
    <div>
        <h2><svg viewBox="0 0 16 16" width="15" height="15" fill="none" stroke="currentColor"
                stroke-width="1.35" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="3.5" y="3.5" width="9" height="9" rx="2" />
                <path d="M6 1v2m4-2v2M6 13v2m4-2v2M1 6h2m10 0h2M1 10h2m10 0h2" />
            </svg>PHP allocated memory</h2>
        <strong>{{ $server['memory']['app_formatted'] }}</strong>
        <small>Peak {{ $server['memory']['app_peak_formatted'] }}</small>
    </div>
    <div>
        <h2><svg viewBox="0 0 16 16" width="15" height="15" fill="none" stroke="currentColor"
                stroke-width="1.35" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <ellipse cx="8" cy="4" rx="5.5" ry="2.3" />
                <path d="M2.5 4v8c0 3.1 11 3.1 11 0V4M2.5 8c0 3.1 11 3.1 11 0" />
            </svg>Filesystem used</h2>
        <div class="storage-reading">
            <strong>{{ $disk !== null ? $disk . '%' : '—' }}</strong>
            @if ($disk !== null)
                <span class="meter {{ $diskLevel }}" aria-hidden="true"><i
                        style="width: {{ min(100, max(0, $disk)) }}%"></i></span>
            @endif
        </div>
        <small>{{ isset($server['disk']['free_formatted']) ? $server['disk']['free_formatted'] . ' available' : 'Unavailable' }}</small>
    </div>
    <div>
        <h2><svg viewBox="0 0 16 16" width="15" height="15" fill="none" stroke="currentColor"
                stroke-width="1.35" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M1 9h3l2.5-6 3 10 2-5H15" />
            </svg>Load average</h2>
        <strong>{{ isset($server['load']['1m']) ? number_format($server['load']['1m'], 2) : '—' }}</strong>
        <small>Over the last minute</small>
    </div>
</section>
