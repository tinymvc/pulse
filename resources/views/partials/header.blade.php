<header class="border-b border-gray-800 bg-gray-900/80 backdrop-blur sticky top-0 z-40 px-6 py-3 flex items-center justify-between">
    <div class="flex items-center space-x-4">
        <a href="{{ $path }}" class="flex items-center space-x-2.5 group">
            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-600 text-white font-bold text-lg shadow-lg shadow-indigo-500/30 group-hover:bg-indigo-500 transition">
                ⚡
            </span>
            <span class="text-xl font-bold tracking-tight bg-gradient-to-r from-white via-gray-200 to-indigo-300 bg-clip-text text-transparent">
                Spark Pulse
            </span>
        </a>
        <span class="px-2.5 py-0.5 text-xs rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-medium flex items-center space-x-1.5">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
            <span>Live Telemetry</span>
        </span>
        <span class="hidden md:inline-block text-xs text-gray-400 border-l border-gray-800 pl-4 font-mono">
            {{ $server['runtime']['app_env'] ?? 'local' }} · PHP {{ $server['runtime']['php_version'] ?? PHP_VERSION }} · Spark {{ $server['runtime']['spark_version'] ?? '4.x' }}
        </span>
    </div>

    <div class="flex items-center space-x-3 text-sm">
        {{-- Polling Toggle --}}
        <button @click="togglePolling()"
            class="px-3 py-1.5 rounded-lg border text-xs font-medium transition flex items-center space-x-1.5"
            :class="isPolling ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20' : 'border-gray-700 bg-gray-800 text-gray-400 hover:bg-gray-700'">
            <span class="w-2 h-2 rounded-full" :class="isPolling ? 'bg-emerald-400 animate-pulse' : 'bg-gray-500'"></span>
            <span x-text="isPolling ? 'Auto-refresh: 3s' : 'Auto-refresh: Paused'"></span>
        </button>

        {{-- Clear Entries Action --}}
        <button @click="clearEntries()"
            class="px-3 py-1.5 rounded-lg border border-red-500/20 bg-red-500/10 hover:bg-red-500/20 text-red-400 text-xs font-medium transition flex items-center space-x-1.5">
            <span>🗑️</span>
            <span>Clear Entries</span>
        </button>
    </div>
</header>
