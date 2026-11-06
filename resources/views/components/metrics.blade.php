@props([
    'server' => [],
    'counts' => [],
])

<div {{ $attributes->merge(['class' => 'space-y-6']) }}>
    {{-- Server Health Metrics Grid --}}
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        {{-- CPU Utilization Card --}}
        <x-pulse::card title="CPU Utilization" icon="⚡">
            <div class="mt-1 flex items-baseline space-x-2">
                <span class="text-3xl font-bold tracking-tight text-white font-mono" x-text="(server.cpu.usage_percent || 0) + '%'"></span>
                <span class="text-xs text-gray-400" x-text="(server.cpu.cores || 1) + ' Cores'"></span>
            </div>
            <div class="w-full bg-gray-800 rounded-full h-2 mt-4 overflow-hidden">
                <div class="bg-indigo-500 h-2 rounded-full transition-all duration-500" :style="'width: ' + Math.min(100, Math.max(0, server.cpu.usage_percent || 0)) + '%'"></div>
            </div>
        </x-pulse::card>

        {{-- Memory (RAM) Card --}}
        <x-pulse::card title="Memory (RAM)" icon="💾">
            <div class="mt-1 flex items-baseline space-x-2">
                <span class="text-3xl font-bold tracking-tight text-white font-mono" x-text="server.memory.app_formatted || '0 MB'"></span>
                <span class="text-xs text-gray-400" x-text="'Peak: ' + (server.memory.app_peak_formatted || '0 MB')"></span>
            </div>
            <div class="w-full bg-gray-800 rounded-full h-2 mt-4 overflow-hidden">
                <div class="bg-purple-500 h-2 rounded-full transition-all duration-500" :style="'width: ' + Math.min(100, Math.max(0, server.memory.usage_percent || 0)) + '%'"></div>
            </div>
        </x-pulse::card>

        {{-- Disk Storage Card --}}
        <x-pulse::card title="Disk Storage" icon="💿">
            <div class="mt-1 flex items-baseline space-x-2">
                <span class="text-3xl font-bold tracking-tight text-white font-mono" x-text="server.disk.used_formatted || '0 GB'"></span>
                <span class="text-xs text-gray-400" x-text="'of ' + (server.disk.total_formatted || '0 GB')"></span>
            </div>
            <div class="w-full bg-gray-800 rounded-full h-2 mt-4 overflow-hidden">
                <div class="bg-blue-500 h-2 rounded-full transition-all duration-500" :style="'width: ' + Math.min(100, Math.max(0, server.disk.usage_percent || 0)) + '%'"></div>
            </div>
        </x-pulse::card>

        {{-- System Load Card --}}
        <x-pulse::card title="System Load" icon="⏱️">
            <div class="mt-1 flex items-baseline space-x-2">
                <span class="text-3xl font-bold tracking-tight text-white font-mono" x-text="server.load['1m'] || '0.00'"></span>
                <span class="text-xs text-gray-400 font-mono" x-text="'5m: ' + (server.load['5m'] || 0) + ' · 15m: ' + (server.load['15m'] || 0)"></span>
            </div>
            <div class="text-xs text-emerald-400 mt-4 flex items-center space-x-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                <span>System Healthy</span>
            </div>
        </x-pulse::card>
    </div>

    {{-- Activity Summary Cards Grid --}}
    <div>
        <div class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-3 px-1">
            Recorded Telemetry Summary
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-7 gap-3">
            <template x-for="item in navItems" :key="item.type">
                <div @click="setTab(item.type)"
                    class="bg-gray-900/50 hover:bg-gray-800/60 border border-gray-800 hover:border-gray-700 rounded-xl p-4 cursor-pointer transition group">
                    <div class="flex items-center justify-between">
                        <span class="text-xl group-hover:scale-110 transition-transform" x-text="item.icon"></span>
                        <span class="text-2xl font-bold text-white font-mono" x-text="counts[item.type] ?? 0"></span>
                    </div>
                    <div class="mt-2 text-xs font-medium text-gray-400 group-hover:text-gray-200" x-text="item.label"></div>
                </div>
            </template>
        </div>
    </div>
</div>
