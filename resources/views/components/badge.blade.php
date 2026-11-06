@props([
    'tone' => 'neutral',
])

@php
$toneClasses = match($tone) {
    'success' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
    'warning' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
    'danger' => 'bg-red-500/10 text-red-400 border-red-500/20',
    'info' => 'bg-indigo-500/10 text-indigo-400 border-indigo-500/20',
    default => 'bg-gray-800 text-gray-300 border-gray-700',
};
@endphp

<span {{ $attributes->merge(['class' => "px-2.5 py-0.5 text-xs rounded-full border font-medium inline-flex items-center space-x-1.5 {$toneClasses}"]) }}>
    {{ $slot }}
</span>
