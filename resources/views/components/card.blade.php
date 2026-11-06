@props([
    'title' => null,
    'icon' => null,
])

<div {{ $attributes->merge(['class' => 'bg-gray-900/70 border border-gray-800 rounded-xl p-5 shadow-sm transition']) }}>
    @if ($title || $icon)
        <div class="flex items-center justify-between text-gray-400 text-xs font-semibold uppercase tracking-wider mb-3">
            <span>{{ $title }}</span>
            @if ($icon)
                <span>{{ $icon }}</span>
            @endif
        </div>
    @endif

    {{ $slot }}
</div>
