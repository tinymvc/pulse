<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h2 class="text-xl font-bold text-white capitalize flex items-center space-x-2.5">
            <span class="text-2xl" x-text="activeIcon"></span>
            <span x-text="activeTab"></span>
            <span class="text-xs px-2.5 py-0.5 rounded-full bg-gray-800 text-gray-400 font-mono font-normal" x-text="(entries.length || 0) + ' entries'"></span>
        </h2>

        {{-- Filter Input --}}
        <div class="flex items-center space-x-2">
            <div class="relative">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-500 text-xs">
                    🔍
                </span>
                <input type="text" x-model="searchQuery" @input.debounce.300ms="fetchEntries()"
                    placeholder="Filter entries..."
                    class="pl-8 pr-3 py-1.5 rounded-lg bg-gray-900 border border-gray-800 text-sm focus:outline-none focus:border-indigo-500 w-64 text-gray-200 placeholder-gray-500 transition">
            </div>
        </div>
    </div>

    {{-- Entries Container --}}
    <div class="bg-gray-900/70 border border-gray-800 rounded-xl overflow-hidden shadow-sm">
        {{-- Loading Indicator --}}
        <div x-show="loading" class="p-10 text-center text-gray-400 text-sm flex items-center justify-center space-x-2">
            <span class="inline-block w-4 h-4 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin"></span>
            <span>Loading entries...</span>
        </div>

        {{-- Empty State --}}
        <div x-show="!loading && entries.length === 0" class="p-14 text-center text-gray-500 text-sm">
            <div class="text-3xl mb-2">📭</div>
            <p>No telemetry entries recorded for <span class="capitalize text-gray-300 font-medium" x-text="activeTab"></span> yet.</p>
        </div>

        {{-- Entries Row List --}}
        <div x-show="!loading && entries.length > 0" class="divide-y divide-gray-800/80">
            <template x-for="entry in entries" :key="entry.id">
                <div @click="selectEntry(entry)"
                    class="p-4 hover:bg-gray-800/40 cursor-pointer transition flex items-center justify-between group">
                    <div class="flex items-center space-x-3 overflow-hidden pr-4">
                        {{-- Tag Badges --}}
                        <div class="flex items-center space-x-1.5 flex-shrink-0">
                            <template x-for="tag in (entry.tags || [])" :key="tag">
                                <span class="px-2 py-0.5 rounded text-xs font-semibold font-mono"
                                    :class="getTagClass(tag)"
                                    x-text="tag">
                                </span>
                            </template>
                        </div>

                        {{-- Main Summary Text --}}
                        <span class="text-sm font-medium text-gray-200 truncate group-hover:text-indigo-300 transition"
                            x-text="getEntrySummary(entry)">
                        </span>
                    </div>

                    <div class="flex items-center space-x-4 text-xs text-gray-400 flex-shrink-0 font-mono">
                        <span x-show="entry.duration !== null" class="px-2 py-0.5 rounded bg-gray-800 text-gray-300" x-text="entry.duration + ' ms'"></span>
                        <span x-text="formatTime(entry.created_at)"></span>
                        <span class="text-gray-600 group-hover:text-gray-300 transition">→</span>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>
