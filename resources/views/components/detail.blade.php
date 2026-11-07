@props(['entry', 'trace', 'labels', 'path', 'filters', 'languageLoads' => []])
@use('Spark\Pulse\Entry')
@use('Spark\Pulse\EntryType')
@use('Spark\Pulse\Recorders\ServerPerformanceRecorder')
<section class="panel detail">
    <div class="section-head">
        <h2>{{ $labels[$entry['type']] ?? $entry['type'] }}</h2>
        @php($date = date_create_immutable($entry['created_at']))
        <time class="mono count" data-local-time="detail"
            datetime="{{ $entry['created_at'] }}">{{ $date ? $date->format('Y-m-d · g:i:s A') . ' UTC' : $entry['created_at'] }}</time>
    </div>
    <div class="detail-body">
        <h3>{{ Entry::summary($entry) }}</h3>
        <div class="tags">
            @php($duration = $entry['content']['duration_ms'] ?? $entry['duration'])
            @if (is_numeric($duration))
                <span class="tag">{{ ServerPerformanceRecorder::formatDuration($duration) }}</span>
            @endif
            @foreach ($entry['tags'] as $tag)
                <span class="tag">{{ $tag }}</span>
            @endforeach
        </div>
        <dl class="facts ids">
            @if ($entry['type'] === EntryType::REQUEST && isset($entry['content']['memory_peak']))
                <div>
                    <dt>Peak PHP process allocation</dt>
                    <dd>{{ ServerPerformanceRecorder::formatBytes($entry['content']['memory_peak']) }}</dd>
                </div>
            @endif
            @if (isset($entry['content']['allocated_change_bytes']))
                <div>
                    <dt>PHP allocation change</dt>
                    <dd>{{ ServerPerformanceRecorder::formatByteChange($entry['content']['allocated_change_bytes']) }}
                    </dd>
                </div>
            @endif
            @if (!empty($languageLoads['count']))
                <div>
                    <dt>Language file loads</dt>
                    <dd>{{ $languageLoads['count'] }}</dd>
                </div>
                @if ($largest = $languageLoads['largest'])
                    <div>
                        <dt>Largest language file allocation increase</dt>
                        <dd><a
                                href="{{ $path . '?' . http_build_query(array_merge($filters, ['entry' => $largest['id']])) }}">{{ ServerPerformanceRecorder::formatByteChange($largest['content']['allocated_change_bytes']) . ' · ' . Entry::summary($largest) }}</a>
                        </dd>
                    </div>
                @endif
            @endif
            <div>
                <dt>Entry ID</dt>
                <dd>{{ $entry['id'] }}</dd>
            </div>
            <div>
                <dt>Batch ID</dt>
                <dd>{{ $entry['batch_id'] }}</dd>
            </div>
        </dl>
        <div class="payload-head">
            <h4>Payload</h4>
            <button type="button" class="control small" data-copy="#payload">Copy</button>
        </div>
        <pre id="payload" tabindex="0">{{ json_encode($entry['content'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) }}</pre>
    </div>
</section>
<section class="panel trace">
    <div class="section-head">
        <h2>Execution trace</h2><span class="count">{{ count($trace) }}
            {{ count($trace) === 1 ? 'entry' : 'entries' }}</span>
    </div>
    <ol>
        @foreach ($trace as $step)
            <li>
                @php($date = date_create_immutable($step['created_at']))
                <time class="mono" data-local-time="trace" datetime="{{ $step['created_at'] }}"
                    title="{{ $step['created_at'] }}">{{ $date ? $date->format('g:i:s.v A') : $step['created_at'] }}</time>
                <div><small>{{ $labels[$step['type']] ?? $step['type'] }}</small>
                    <a href="{{ $path . '?' . http_build_query(array_merge($filters, ['entry' => $step['id']])) }}"
                        @if ($step['id'] === $entry['id']) aria-current="page" @endif>{{ Entry::summary($step) }}</a>
                    @php
                        $elapsed = $step['content']['duration_ms'] ?? $step['duration'];
                        $metrics = [];
                        if (is_numeric($elapsed)) {
                            $metrics[] = ServerPerformanceRecorder::formatDuration($elapsed);
                        }
                        if (isset($step['content']['allocated_change_bytes'])) {
                            $metrics[] =
                                'PHP allocation ' .
                                ServerPerformanceRecorder::formatByteChange($step['content']['allocated_change_bytes']);
                        }
                    @endphp
                    @if ($metrics)
                        <small>{{ implode(' · ', $metrics) }}</small>
                    @endif
                </div>
            </li>
        @endforeach
    </ol>
</section>
