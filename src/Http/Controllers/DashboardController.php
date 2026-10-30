<?php

namespace Spark\Pulse\Http\Controllers;

use Spark\Http\Request;
use Spark\Http\Response;
use Spark\Pulse\EntryType;
use Spark\Pulse\Pulse;
use Spark\Pulse\Recorders\ServerPerformanceRecorder;

/**
 * Pulse Dashboard Controller.
 *
 * @package Spark\Pulse\Http\Controllers
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class DashboardController
{
    /**
     * Render the main Pulse dashboard view.
     */
    public function index(Request $request): Response
    {
        $pulse = Pulse::instance();

        // Collect high-level counts for all 14 modules
        $counts = [];
        foreach (EntryType::all() as $type) {
            $counts[$type] = $pulse->storage()->count($type);
        }

        $server = ServerPerformanceRecorder::sample();

        // Render blade view with fallback inline rendering if blade template is missing
        try {
            return Response::from(view('pulse::dashboard', [
                'counts' => $counts,
                'server' => $server,
                'pulse' => $pulse,
                'activeTab' => $request->query('tab', 'dashboard'),
            ]));
        } catch (\Throwable) {
            // Provide a modern built-in self-contained HTML page
            return Response::from(self::renderStandaloneDashboard($counts, $server, $request->query('tab', 'dashboard')));
        }
    }

    /**
     * Fallback standalone HTML dashboard when views are not published yet.
     */
    public static function renderStandaloneDashboard(array $counts, array $server, string $activeTab): string
    {
        $path = '/' . ltrim(config('pulse.path', 'pulse'), '/');
        $jsonCounts = json_encode($counts);
        $jsonServer = json_encode($server);

        return <<<HTML
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spark Pulse · Application & Server Telemetry</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#eef2ff',
                            500: '#6366f1',
                            600: '#4f46e5',
                            700: '#4338ca',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Figtree', sans-serif; }
        [x-cloak] { display: none !important; }
    </style>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-950 text-gray-100 min-h-screen antialiased flex flex-col" x-data="pulseApp()" x-init="init()">
    <!-- Top Navigation Header -->
    <header class="border-b border-gray-800 bg-gray-900/80 backdrop-blur sticky top-0 z-40 px-6 py-3 flex items-center justify-between">
        <div class="flex items-center space-x-4">
            <div class="flex items-center space-x-2">
                <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-indigo-600 text-white font-bold text-lg shadow-lg shadow-indigo-500/30">
                    ⚡
                </span>
                <span class="text-xl font-bold tracking-tight bg-gradient-to-r from-white via-gray-200 to-indigo-300 bg-clip-text text-transparent">
                    Spark Pulse
                </span>
            </div>
            <span class="px-2 py-0.5 text-xs rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-medium">
                Live Telemetry
            </span>
            <span class="text-xs text-gray-400">
                {$server['runtime']['app_env']} · PHP {$server['runtime']['php_version']} · Spark {$server['runtime']['spark_version']}
            </span>
        </div>

        <div class="flex items-center space-x-3 text-sm">
            <!-- Polling toggle -->
            <button @click="togglePolling()" class="px-3 py-1.5 rounded-lg border text-xs font-medium transition flex items-center space-x-1.5"
                :class="isPolling ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-400' : 'border-gray-700 bg-gray-800 text-gray-400'">
                <span class="w-2 h-2 rounded-full" :class="isPolling ? 'bg-emerald-400 animate-pulse' : 'bg-gray-500'"></span>
                <span x-text="isPolling ? 'Auto-refresh: 3s' : 'Auto-refresh: Paused'"></span>
            </button>

            <!-- Clear Action -->
            <button @click="clearEntries()" class="px-3 py-1.5 rounded-lg border border-red-500/20 bg-red-500/10 hover:bg-red-500/20 text-red-400 text-xs font-medium transition">
                Clear Entries
            </button>
        </div>
    </header>

    <!-- Main Body Container -->
    <div class="flex-1 flex overflow-hidden">
        <!-- Sidebar Navigation -->
        <aside class="w-64 border-r border-gray-800 bg-gray-900/50 p-4 space-y-1 overflow-y-auto">
            <div class="px-3 py-2 text-xs font-semibold uppercase tracking-wider text-gray-500">
                Telemetry Overview
            </div>
            <button @click="setTab('dashboard')" class="w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium transition text-left"
                :class="activeTab === 'dashboard' ? 'bg-indigo-600 text-white shadow' : 'text-gray-300 hover:bg-gray-800/60'">
                <span class="flex items-center space-x-2.5">
                    <span>📊</span>
                    <span>Dashboard</span>
                </span>
            </button>

            <div class="pt-4 px-3 py-2 text-xs font-semibold uppercase tracking-wider text-gray-500">
                Application Activity
            </div>

            <template x-for="item in navItems" :key="item.type">
                <button @click="setTab(item.type)" class="w-full flex items-center justify-between px-3 py-2 rounded-lg text-sm font-medium transition text-left"
                    :class="activeTab === item.type ? 'bg-indigo-600 text-white shadow' : 'text-gray-300 hover:bg-gray-800/60'">
                    <span class="flex items-center space-x-2.5">
                        <span x-text="item.icon"></span>
                        <span x-text="item.label"></span>
                    </span>
                    <span class="text-xs px-2 py-0.5 rounded-full"
                        :class="activeTab === item.type ? 'bg-indigo-700 text-white' : 'bg-gray-800 text-gray-400'"
                        x-text="counts[item.type] ?? 0">
                    </span>
                </button>
            </template>
        </aside>

        <!-- Main Content Area -->
        <main class="flex-1 overflow-y-auto p-6 bg-gray-950">
            <!-- TAB: Dashboard (Overview & Server Metrics) -->
            <div x-show="activeTab === 'dashboard'" class="space-y-6">
                <!-- Server Metrics Grid -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <!-- CPU Card -->
                    <div class="bg-gray-900/70 border border-gray-800 rounded-xl p-5 shadow-sm">
                        <div class="flex items-center justify-between text-gray-400 text-xs font-semibold uppercase tracking-wider">
                            <span>CPU Utilization</span>
                            <span>⚡</span>
                        </div>
                        <div class="mt-3 flex items-baseline space-x-2">
                            <span class="text-3xl font-bold tracking-tight text-white" x-text="(server.cpu.usage_percent || 0) + '%'"></span>
                            <span class="text-xs text-gray-400" x-text="(server.cpu.cores || 1) + ' Cores'"></span>
                        </div>
                        <div class="w-full bg-gray-800 rounded-full h-2 mt-4 overflow-hidden">
                            <div class="bg-indigo-500 h-2 rounded-full transition-all duration-500" :style="'width: ' + (server.cpu.usage_percent || 0) + '%'"></div>
                        </div>
                    </div>

                    <!-- Memory Card -->
                    <div class="bg-gray-900/70 border border-gray-800 rounded-xl p-5 shadow-sm">
                        <div class="flex items-center justify-between text-gray-400 text-xs font-semibold uppercase tracking-wider">
                            <span>Memory (RAM)</span>
                            <span>💾</span>
                        </div>
                        <div class="mt-3 flex items-baseline space-x-2">
                            <span class="text-3xl font-bold tracking-tight text-white" x-text="server.memory.app_formatted || '0 MB'"></span>
                            <span class="text-xs text-gray-400" x-text="'Peak: ' + (server.memory.app_peak_formatted || '0 MB')"></span>
                        </div>
                        <div class="w-full bg-gray-800 rounded-full h-2 mt-4 overflow-hidden">
                            <div class="bg-purple-500 h-2 rounded-full transition-all duration-500" :style="'width: ' + (server.memory.usage_percent || 0) + '%'"></div>
                        </div>
                    </div>

                    <!-- Disk Space Card -->
                    <div class="bg-gray-900/70 border border-gray-800 rounded-xl p-5 shadow-sm">
                        <div class="flex items-center justify-between text-gray-400 text-xs font-semibold uppercase tracking-wider">
                            <span>Disk Storage</span>
                            <span>💿</span>
                        </div>
                        <div class="mt-3 flex items-baseline space-x-2">
                            <span class="text-3xl font-bold tracking-tight text-white" x-text="server.disk.used_formatted || '0 GB'"></span>
                            <span class="text-xs text-gray-400" x-text="'of ' + (server.disk.total_formatted || '0 GB')"></span>
                        </div>
                        <div class="w-full bg-gray-800 rounded-full h-2 mt-4 overflow-hidden">
                            <div class="bg-blue-500 h-2 rounded-full transition-all duration-500" :style="'width: ' + (server.disk.usage_percent || 0) + '%'"></div>
                        </div>
                    </div>

                    <!-- System Load Card -->
                    <div class="bg-gray-900/70 border border-gray-800 rounded-xl p-5 shadow-sm">
                        <div class="flex items-center justify-between text-gray-400 text-xs font-semibold uppercase tracking-wider">
                            <span>System Load</span>
                            <span>⏱️</span>
                        </div>
                        <div class="mt-3 flex items-baseline space-x-2">
                            <span class="text-3xl font-bold tracking-tight text-white" x-text="server.load['1m'] || '0.00'"></span>
                            <span class="text-xs text-gray-400" x-text="'5m: ' + (server.load['5m'] || 0) + ' · 15m: ' + (server.load['15m'] || 0)"></span>
                        </div>
                        <div class="text-xs text-emerald-400 mt-4 flex items-center space-x-1">
                            <span>System Healthy</span>
                        </div>
                    </div>
                </div>

                <!-- Activity Summary Cards -->
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-7 gap-3">
                    <template x-for="item in navItems" :key="item.type">
                        <div @click="setTab(item.type)" class="bg-gray-900/50 hover:bg-gray-800/60 border border-gray-800 rounded-xl p-4 cursor-pointer transition">
                            <div class="flex items-center justify-between">
                                <span class="text-lg" x-text="item.icon"></span>
                                <span class="text-xl font-bold text-white" x-text="counts[item.type] ?? 0"></span>
                            </div>
                            <div class="mt-2 text-xs font-medium text-gray-400" x-text="item.label"></div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- TAB: Module Entries Table & Waterfall Detail -->
            <div x-show="activeTab !== 'dashboard'" class="space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-xl font-bold text-white capitalize flex items-center space-x-2">
                        <span x-text="activeIcon"></span>
                        <span x-text="activeTab"></span>
                    </h2>

                    <!-- Search and filters -->
                    <div class="flex items-center space-x-2">
                        <input type="text" x-model="searchQuery" @input.debounce.300ms="fetchEntries()"
                            placeholder="Filter entries..."
                            class="px-3 py-1.5 rounded-lg bg-gray-900 border border-gray-800 text-sm focus:outline-none focus:border-indigo-500 w-64 text-gray-200">
                    </div>
                </div>

                <!-- Entries Table -->
                <div class="bg-gray-900/70 border border-gray-800 rounded-xl overflow-hidden">
                    <div x-show="loading" class="p-8 text-center text-gray-400 text-sm">
                        Loading entries...
                    </div>

                    <div x-show="!loading && entries.length === 0" class="p-12 text-center text-gray-500 text-sm">
                        No entries recorded for <span class="capitalize" x-text="activeTab"></span> yet.
                    </div>

                    <div x-show="!loading && entries.length > 0" class="divide-y divide-gray-800">
                        <template x-for="entry in entries" :key="entry.id">
                            <div @click="selectEntry(entry)" class="p-4 hover:bg-gray-800/40 cursor-pointer transition flex items-center justify-between">
                                <div class="flex items-center space-x-3 overflow-hidden">
                                    <!-- Badges -->
                                    <template x-for="tag in (entry.tags || [])" :key="tag">
                                        <span class="px-2 py-0.5 rounded text-xs font-semibold"
                                            :class="getTagClass(tag)"
                                            x-text="tag">
                                        </span>
                                    </template>

                                    <!-- Main Title/Summary -->
                                    <span class="text-sm font-medium text-gray-200 truncate" x-text="getEntrySummary(entry)"></span>
                                </div>

                                <div class="flex items-center space-x-4 text-xs text-gray-400 flex-shrink-0">
                                    <span x-show="entry.duration !== null" class="font-mono text-gray-300" x-text="entry.duration + ' ms'"></span>
                                    <span x-text="formatTime(entry.created_at)"></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Slide-over Drawer for Entry Detail & Trace Waterfall -->
    <div x-show="selectedEntry !== null" x-cloak class="fixed inset-0 z-50 overflow-hidden bg-black/60 backdrop-blur-sm flex justify-end" @keydown.window.escape="selectedEntry = null">
        <div class="w-full max-w-2xl bg-gray-900 border-l border-gray-800 h-full p-6 overflow-y-auto space-y-6 flex flex-col" @click.outside="selectedEntry = null">
            <div class="flex items-center justify-between border-b border-gray-800 pb-4">
                <div class="flex items-center space-x-2">
                    <span class="px-2.5 py-1 rounded bg-indigo-500/20 text-indigo-300 text-xs font-semibold uppercase" x-text="selectedEntry?.type"></span>
                    <span class="text-sm font-mono text-gray-400" x-text="'ID: ' + selectedEntry?.id"></span>
                </div>
                <button @click="selectedEntry = null" class="text-gray-400 hover:text-white text-lg">✕</button>
            </div>

            <!-- Trace Waterfall Correlation Info -->
            <div class="bg-gray-950/80 border border-gray-800 rounded-lg p-4 space-y-2">
                <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Trace Correlation</div>
                <div class="text-xs font-mono text-indigo-400" x-text="'Batch ID: ' + selectedEntry?.batch_id"></div>
                <button @click="fetchBatchTrace(selectedEntry?.batch_id)" class="text-xs text-indigo-400 underline hover:text-indigo-300">
                    View full correlated waterfall trace (queries, logs, cache, views in this execution)
                </button>
            </div>

            <!-- Content JSON Viewer -->
            <div class="flex-1">
                <div class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-2">Payload Data</div>
                <pre class="bg-gray-950 border border-gray-800 rounded-lg p-4 text-xs font-mono text-gray-300 overflow-x-auto" x-text="JSON.stringify(selectedEntry?.content, null, 2)"></pre>
            </div>
        </div>
    </div>

    <script>
        function pulseApp() {
            return {
                counts: {$jsonCounts},
                server: {$jsonServer},
                activeTab: '{$activeTab}',
                entries: [],
                loading: false,
                isPolling: true,
                pollingInterval: null,
                selectedEntry: null,
                searchQuery: '',

                navItems: [
                    { type: 'request', label: 'Requests', icon: '🌐' },
                    { type: 'command', label: 'Commands', icon: '💻' },
                    { type: 'job', label: 'Jobs', icon: '⚡' },
                    { type: 'cache', label: 'Cache', icon: '📦' },
                    { type: 'event', label: 'Events', icon: '📢' },
                    { type: 'exception', label: 'Exceptions', icon: '💥' },
                    { type: 'gate', label: 'Gates', icon: '🔒' },
                    { type: 'http_client', label: 'HTTP Client', icon: '🌍' },
                    { type: 'log', label: 'Logs', icon: '📝' },
                    { type: 'mail', label: 'Mail', icon: '✉️' },
                    { type: 'model', label: 'Models', icon: '🗄️' },
                    { type: 'query', label: 'Queries', icon: '🔍' },
                    { type: 'view', label: 'Views', icon: '🎨' },
                    { type: 'performance', label: 'Performance', icon: '📈' },
                ],

                get activeIcon() {
                    const item = this.navItems.find(i => i.type === this.activeTab);
                    return item ? item.icon : '📊';
                },

                init() {
                    this.startPolling();
                },

                setTab(tab) {
                    this.activeTab = tab;
                    if (tab !== 'dashboard') {
                        this.fetchEntries();
                    }
                },

                startPolling() {
                    this.isPolling = true;
                    if (this.pollingInterval) clearInterval(this.pollingInterval);
                    this.pollingInterval = setInterval(() => {
                        this.refreshMetrics();
                        if (this.activeTab !== 'dashboard') {
                            this.fetchEntries(true);
                        }
                    }, 3000);
                },

                togglePolling() {
                    if (this.isPolling) {
                        clearInterval(this.pollingInterval);
                        this.isPolling = false;
                    } else {
                        this.startPolling();
                    }
                },

                async refreshMetrics() {
                    try {
                        const res = await fetch('{$path}/api/performance');
                        if (res.ok) {
                            const data = await res.json();
                            this.server = data.server || this.server;
                            this.counts = data.counts || this.counts;
                        }
                    } catch (e) {}
                },

                async fetchEntries(silent = false) {
                    if (!silent) this.loading = true;
                    try {
                        const params = new URLSearchParams({
                            type: this.activeTab,
                            search: this.searchQuery,
                        });
                        const res = await fetch(`{$path}/api/entries?\${params.toString()}`);
                        if (res.ok) {
                            const data = await res.json();
                            this.entries = data.entries || [];
                        }
                    } catch (e) {
                    } finally {
                        if (!silent) this.loading = false;
                    }
                },

                selectEntry(entry) {
                    this.selectedEntry = entry;
                },

                async clearEntries() {
                    if (!confirm('Are you sure you want to clear all Pulse recorded entries?')) return;
                    try {
                        await fetch('{$path}/api/clear', { method: 'POST' });
                        this.entries = [];
                        for (let k in this.counts) this.counts[k] = 0;
                    } catch (e) {}
                },

                getTagClass(tag) {
                    tag = (tag || '').toLowerCase();
                    if (['error', 'failed', 'denied'].includes(tag)) return 'bg-red-500/20 text-red-300';
                    if (['slow', 'duplicate'].includes(tag)) return 'bg-amber-500/20 text-amber-300';
                    if (['success', 'allowed', 'hit', 'sent', 'processed'].includes(tag)) return 'bg-emerald-500/20 text-emerald-300';
                    if (['post', 'written'].includes(tag)) return 'bg-blue-500/20 text-blue-300';
                    return 'bg-gray-800 text-gray-300';
                },

                getEntrySummary(entry) {
                    const c = entry.content || {};
                    switch (entry.type) {
                        case 'request': return `\${c.method || 'GET'} \${c.uri || '/'}`;
                        case 'query': return c.sql || 'SQL Query';
                        case 'exception': return `\${c.class || 'Exception'}: \${c.message || ''}`;
                        case 'command': return `spark \${c.name || ''}`;
                        case 'job': return c.name || 'Queue Job';
                        case 'cache': return `[\${c.action || ''}] \${c.key || ''}`;
                        case 'event': return c.name || 'Event';
                        case 'gate': return `Gate: \${c.ability || ''} (\${c.result ? 'Allowed' : 'Denied'})`;
                        case 'http_client': return `\${c.method || 'GET'} \${c.url || ''}`;
                        case 'log': return `[\${c.level || ''}] \${c.message || ''}`;
                        case 'mail': return `Mail to \${(c.to || []).join(', ')}: \${c.subject || ''}`;
                        case 'model': return `\${c.model || ''} (\${c.action || ''})`;
                        case 'view': return `View: \${c.template || c.path || ''}`;
                        default: return JSON.stringify(c).substring(0, 80);
                    }
                },

                formatTime(iso) {
                    if (!iso) return '';
                    const d = new Date(iso);
                    return d.toLocaleTimeString();
                }
            }
        }
    </script>
</body>
</html>
HTML;
    }
}
