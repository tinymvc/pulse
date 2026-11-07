@props(['entry', 'trace', 'labels', 'path', 'filters'])
@use('Spark\Pulse\Entry')
<section class="panel detail">
    <div class="section-head"><h2>{{ $labels[$entry['type']] ?? $entry['type'] }}</h2><time class="mono" datetime="{{ $entry['created_at'] }}">{{ substr($entry['created_at'], 0, 10) }} · {{ substr($entry['created_at'], 11, 8) }} UTC</time></div>
    <div class="detail-body">
        <h3>{{ Entry::summary($entry) }}</h3>
        <div class="tags">
            @if($entry['duration'] !== null)
                <span class="tag">{{ $entry['duration'] }} ms</span>
            @endif
            @foreach($entry['tags'] as $tag)
                <span class="tag">{{ $tag }}</span>
            @endforeach
        </div>
        <dl class="facts ids">
            <div><dt>Entry ID</dt><dd>{{ $entry['id'] }}</dd></div>
            <div><dt>Batch ID</dt><dd>{{ $entry['batch_id'] }}</dd></div>
        </dl>
        <h4>Payload</h4>
        <pre tabindex="0">{{ json_encode($entry['content'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) }}</pre>
    </div>
</section>
<section class="panel trace">
    <div class="section-head"><h2>Execution trace</h2><span class="count">{{ count($trace) }} {{ count($trace) === 1 ? 'entry' : 'entries' }}</span></div>
    <ol>
        @foreach($trace as $step)
            <li>
                <span class="mono">{{ substr($step['created_at'], 11, 12) }}</span>
                <div><small>{{ $labels[$step['type']] ?? $step['type'] }}</small>
                    <a href="{{ $path . '?' . http_build_query(array_merge($filters, ['entry' => $step['id']])) }}" @if($step['id'] === $entry['id']) aria-current="page" @endif>{{ Entry::summary($step) }}</a>
                </div>
            </li>
        @endforeach
    </ol>
</section>
