<aside class="w-64 border-r border-gray-800 bg-gray-900/50 p-4 space-y-1 overflow-y-auto flex-shrink-0">
    <div class="px-3 py-2 text-xs font-semibold uppercase tracking-wider text-gray-500">
        Telemetry Overview
    </div>

    <button @click="setTab('dashboard')"
        class="w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium transition text-left"
        :class="activeTab === 'dashboard' ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/20' : 'text-gray-300 hover:bg-gray-800/60'">
        <span class="flex items-center space-x-2.5">
            <span>📊</span>
            <span>Dashboard</span>
        </span>
    </button>

    <div class="pt-5 px-3 py-2 text-xs font-semibold uppercase tracking-wider text-gray-500">
        Application Activity
    </div>

    <template x-for="item in navItems" :key="item.type">
        <button @click="setTab(item.type)"
            class="w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium transition text-left"
            :class="activeTab === item.type ? 'bg-indigo-600 text-white shadow-md shadow-indigo-500/20' : 'text-gray-300 hover:bg-gray-800/60'">
            <span class="flex items-center space-x-2.5">
                <span x-text="item.icon"></span>
                <span x-text="item.label"></span>
            </span>
            <span class="text-xs px-2 py-0.5 rounded-full font-mono"
                :class="activeTab === item.type ? 'bg-indigo-700 text-white' : 'bg-gray-800 text-gray-400'"
                x-text="counts[item.type] ?? 0">
            </span>
        </button>
    </template>
</aside>
