@props(['counts', 'server', 'path'])
@php
    $total = array_sum($counts);
    $distribution = [
        'Requests' => $counts['request'],
        'Queries' => $counts['query'],
        'Logs' => $counts['log'],
        'Other' => $total - $counts['request'] - $counts['query'] - $counts['log'],
    ];
@endphp
<section class="summary-grid" aria-label="Telemetry summary" data-live="summary">
    <div class="activity panel">
        <h2>Recorded activity</h2>
        <div class="total">{{ number_format($total) }} <span>{{ $total === 1 ? 'entry' : 'entries' }}</span></div>
        <div class="distribution" aria-hidden="true">
            @foreach ($distribution as $label => $count)
                <i class="series series-{{ strtolower($label) }}"
                    style="width: {{ $total ? (100 * $count) / $total : 0 }}%"></i>
            @endforeach
        </div>
        <dl class="legend">
            @foreach ($distribution as $label => $count)
                <div>
                    <dt><i class="series-{{ strtolower($label) }}"></i>{{ $label }}</dt>
                    <dd>{{ number_format($count) }}</dd>
                </div>
            @endforeach
        </dl>
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
        <h2>PHP memory</h2><strong>{{ $server['memory']['app_formatted'] }}</strong><small>Peak
            {{ $server['memory']['app_peak_formatted'] }}</small>
    </div>
    <div>
        <h2>Storage used</h2>
        <strong>{{ isset($server['disk']['usage_percent']) ? $server['disk']['usage_percent'] . '%' : '—' }}</strong><small>{{ isset($server['disk']['free_formatted']) ? $server['disk']['free_formatted'] . ' available' : 'Unavailable' }}</small>
    </div>
    <div>
        <h2>Load average</h2>
        <strong>{{ isset($server['load']['1m']) ? number_format($server['load']['1m'], 2) : '—' }}</strong><small>Over
            the last minute</small>
    </div>
</section>
