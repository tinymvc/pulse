@props(['page', 'overview', 'path', 'filters', 'search', 'tab' => null])
@use('Spark\Pulse\Entry')
@use('Spark\Pulse\EntryType')
@use('Spark\Pulse\Recorders\ServerPerformanceRecorder')
@php
    $languageFiles = $tab === EntryType::LANGUAGE_FILE;
    $statusClass = fn($status) => match (true) {
        !is_numeric($status) => '',
        (int) $status >= 500 => 'tag-err',
        (int) $status >= 400 => 'tag-warn',
        (int) $status >= 300 => 'tag-info',
        default => 'tag-ok',
    };
@endphp
<section class="panel entries{{ $overview ? ' recent' : '' }}" data-live="entries">
    <div class="section-head">
        <h2>{{ $overview ? 'Recent requests' : 'Recorded entries' }}</h2>
        @if ($overview)
            <a class="text-link" href="{{ $path }}?tab=request">View all requests <span
                    aria-hidden="true">→</span></a>
        @else
            <span class="count">{{ number_format(count($page['entries'])) }} shown</span>
        @endif
    </div>
    @if ($languageFiles)
        <p class="allocation-note">A load can show 0 B when PHP reuses memory it already reserved.</p>
    @endif
    @if (!empty($page['entries']))
        <div class="scroll-table">
            <table>
                <thead>
                    <tr>
                        <th>{{ $languageFiles ? 'File' : 'Entry' }}</th>
                        <th>{{ $languageFiles ? 'PHP allocation change' : ($overview ? 'Status' : 'Tags') }}</th>
                        <th>{{ $languageFiles ? 'Load time' : 'Duration' }}</th>
                        <th data-time-heading>Time (UTC)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($page['entries'] as $row)
                        @php
                            $date = date_create_immutable($row['created_at']);
                            $summary = Entry::summary($row);
                            $link = $path . '?' . http_build_query(array_merge($filters, ['entry' => $row['id']]));
                            $tags = $overview ? [$row['content']['status'] ?? '—'] : array_slice($row['tags'], 0, 2);
                        @endphp
                        <tr>
                            <td><a class="row-link" href="{{ $link }}"
                                    title="{{ $summary }}">{{ $summary }}</a></td>
                            @if ($languageFiles)
                                <td class="mono">
                                    {{ isset($row['content']['allocated_change_bytes']) ? ServerPerformanceRecorder::formatByteChange($row['content']['allocated_change_bytes']) : '—' }}
                                </td>
                                <td class="mono">
                                    {{ isset($row['content']['duration_ms']) ? ServerPerformanceRecorder::formatDuration($row['content']['duration_ms']) : '—' }}
                                </td>
                            @else
                                <td>
                                    <div class="tags">
                                        @foreach ($tags as $tag)
                                            <span
                                                class="tag {{ $overview ? $statusClass($tag) : '' }}">{{ $tag }}</span>
                                        @endforeach
                                    </div>
                                </td>
                                <td class="mono">{{ $row['duration'] !== null ? $row['duration'] . ' ms' : '—' }}
                                </td>
                            @endif
                            <td class="mono"><time data-local-time="clock" datetime="{{ $row['created_at'] }}"
                                    title="{{ $row['created_at'] }}">{{ $date ? $date->format('g:i:s A') : $row['created_at'] }}</time>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="empty">
            <span class="empty-mark" aria-hidden="true">∿</span>
            <h3>{{ $search !== '' ? 'No matching entries' : ($overview ? 'No requests recorded yet' : 'No entries recorded yet') }}
            </h3>
            <p>{{ $search !== '' ? 'Try a different search.' : ($overview ? 'Visit a route in your application to see its request here.' : 'New activity of this type will appear here.') }}
            </p>
        </div>
    @endif
    @if (!$overview && ($page['has_more'] || isset($filters['before'])))
        <div class="pager">
            @if (isset($filters['before']))
                <a class="control"
                    href="{{ $path . '?' . http_build_query(array_diff_key($filters, ['before' => true, 'before_id' => true])) }}">←
                    Newest entries</a>
            @endif
            @if ($page['has_more'])
                @php($last = $page['entries'][array_key_last($page['entries'])])
                <a class="control"
                    href="{{ $path . '?' . http_build_query(array_merge($filters, ['before' => $last['created_at'], 'before_id' => $last['id']])) }}">Older
                    entries →</a>
            @endif
        </div>
    @endif
</section>
