<div x-show="selectedEntry !== null" x-cloak
    class="fixed inset-0 z-50 overflow-hidden bg-black/60 backdrop-blur-sm flex justify-end"
    @keydown.window.escape="selectedEntry = null">
    <div class="w-full max-w-2xl bg-gray-900 border-l border-gray-800 h-full p-6 overflow-y-auto space-y-6 flex flex-col shadow-2xl"
        @click.outside="selectedEntry = null">
        {{-- Drawer Header --}}
        <div class="flex items-center justify-between border-b border-gray-800 pb-4">
            <div class="flex items-center space-x-2.5">
                <span
                    class="px-2.5 py-1 rounded-md bg-indigo-500/20 text-indigo-300 text-xs font-semibold uppercase tracking-wider font-mono"
                    x-text="selectedEntry?.type">
                </span>
                <span class="text-xs font-mono text-gray-400" x-text="'ID: ' + selectedEntry?.id"></span>
            </div>
            <button @click="selectedEntry = null"
                class="text-gray-400 hover:text-white p-1 rounded-md hover:bg-gray-800 transition text-base">
                ✕
            </button>
        </div>

        {{-- Trace Waterfall Correlation Box --}}
        <div class="bg-gray-950/80 border border-gray-800 rounded-lg p-4 space-y-2.5">
            <div class="text-xs font-semibold uppercase tracking-wider text-gray-500 flex items-center justify-between">
                <span>Trace Waterfall Correlation</span>
                <span class="text-indigo-400">⚡ Correlated Trace</span>
            </div>
            <div class="text-xs font-mono text-indigo-300 break-all" x-text="'Batch ID: ' + selectedEntry?.batch_id">
            </div>
            <div class="pt-1">
                <button @click="fetchBatchTrace(selectedEntry?.batch_id)"
                    class="text-xs text-indigo-400 hover:text-indigo-300 underline font-medium flex items-center space-x-1">
                    <span>View full correlated waterfall trace (queries, logs, cache, views in this execution)</span>
                    <span>→</span>
                </button>
            </div>

            {{-- Correlated entries list if loaded --}}
            <div x-show="batchWaterfall !== null" class="mt-3 pt-3 border-t border-gray-800 space-y-1.5">
                <div class="text-xs text-gray-400 font-semibold"
                    x-text="'Correlated Steps (' + (batchWaterfall?.entries?.length || 0) + '):'"></div>
                <div class="max-h-48 overflow-y-auto space-y-1 pr-1">
                    <template x-for="item in (batchWaterfall?.entries || [])" :key="item.id">
                        <div
                            class="text-xs p-2 rounded bg-gray-900 border border-gray-800/80 flex items-center justify-between">
                            <div class="flex items-center space-x-2 overflow-hidden">
                                <span
                                    class="px-1.5 py-0.5 rounded text-[10px] font-mono uppercase bg-indigo-500/10 text-indigo-400"
                                    x-text="item.type"></span>
                                <span class="text-gray-300 truncate" x-text="getEntrySummary(item)"></span>
                            </div>
                            <span class="text-gray-500 font-mono text-[11px] flex-shrink-0"
                                x-text="item.duration ? item.duration + ' ms' : ''"></span>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        {{-- Payload JSON Viewer --}}
        <div class="flex-1 flex flex-col">
            <div class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2">
                Structured Payload Data
            </div>
            <pre class="flex-1 bg-gray-950 border border-gray-800 rounded-lg p-4 text-xs font-mono text-gray-300 overflow-x-auto selection:bg-indigo-500/30"
                x-text="JSON.stringify(selectedEntry?.content, null, 2)"></pre>
        </div>
    </div>
</div>
