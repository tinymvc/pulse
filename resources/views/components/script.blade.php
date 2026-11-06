@props([
    'counts' => [],
    'server' => [],
    'activeTab' => 'dashboard',
    'path' => '/pulse',
])

<script>
    function pulseApp() {
        return {
            counts: @json($counts),
            server: @json($server),
            activeTab: '{{ $activeTab }}',
            basePath: '{{ $path }}',
            entries: [],
            loading: false,
            isPolling: true,
            pollingInterval: null,
            selectedEntry: null,
            batchWaterfall: null,
            searchQuery: '',

            navItems: [{
                    type: 'request',
                    label: 'Requests',
                    icon: '🌐'
                },
                {
                    type: 'command',
                    label: 'Commands',
                    icon: '💻'
                },
                {
                    type: 'job',
                    label: 'Jobs',
                    icon: '⚡'
                },
                {
                    type: 'cache',
                    label: 'Cache',
                    icon: '📦'
                },
                {
                    type: 'event',
                    label: 'Events',
                    icon: '📢'
                },
                {
                    type: 'exception',
                    label: 'Exceptions',
                    icon: '💥'
                },
                {
                    type: 'gate',
                    label: 'Gates',
                    icon: '🔒'
                },
                {
                    type: 'http_client',
                    label: 'HTTP Client',
                    icon: '🌍'
                },
                {
                    type: 'log',
                    label: 'Logs',
                    icon: '📝'
                },
                {
                    type: 'mail',
                    label: 'Mail',
                    icon: '✉️'
                },
                {
                    type: 'model',
                    label: 'Models',
                    icon: '🗄️'
                },
                {
                    type: 'query',
                    label: 'Queries',
                    icon: '🔍'
                },
                {
                    type: 'view',
                    label: 'Views',
                    icon: '🎨'
                },
                {
                    type: 'performance',
                    label: 'Performance',
                    icon: '📈'
                },
            ],

            get activeIcon() {
                const item = this.navItems.find(i => i.type === this.activeTab);
                return item ? item.icon : '📊';
            },

            init() {
                this.startPolling();
                if (this.activeTab !== 'dashboard') {
                    this.fetchEntries();
                }
            },

            setTab(tab) {
                this.activeTab = tab;
                this.searchQuery = '';
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
                    const res = await fetch(`${this.basePath}/api/performance`);
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
                    const res = await fetch(`${this.basePath}/api/entries?${params.toString()}`);
                    if (res.ok) {
                        const data = await res.json();
                        this.entries = data.entries || [];
                    }
                } catch (e) {} finally {
                    if (!silent) this.loading = false;
                }
            },

            selectEntry(entry) {
                this.selectedEntry = entry;
                this.batchWaterfall = null;
            },

            async fetchBatchTrace(batchId) {
                if (!batchId) return;
                try {
                    const res = await fetch(`${this.basePath}/api/batch/${batchId}`);
                    if (res.ok) {
                        this.batchWaterfall = await res.json();
                    }
                } catch (e) {}
            },

            async clearEntries() {
                if (!confirm('Are you sure you want to clear all Pulse recorded entries?')) return;
                try {
                    await fetch(`${this.basePath}/api/clear`, {
                        method: 'POST'
                    });
                    this.entries = [];
                    for (let k in this.counts) this.counts[k] = 0;
                } catch (e) {}
            },

            getTagClass(tag) {
                tag = (tag || '').toLowerCase();
                if (['error', 'failed', 'denied'].includes(tag))
                    return 'bg-red-500/20 text-red-300 border border-red-500/30';
                if (['slow', 'duplicate'].includes(tag))
                    return 'bg-amber-500/20 text-amber-300 border border-amber-500/30';
                if (['success', 'allowed', 'hit', 'sent', 'processed'].includes(tag))
                    return 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30';
                if (['post', 'written'].includes(tag)) return 'bg-blue-500/20 text-blue-300 border border-blue-500/30';
                return 'bg-gray-800 text-gray-300 border border-gray-700';
            },

            getEntrySummary(entry) {
                const c = entry.content || {};
                switch (entry.type) {
                    case 'request':
                        return `${c.method || 'GET'} ${c.uri || '/'}`;
                    case 'query':
                        return c.sql || 'SQL Query';
                    case 'exception':
                        return `${c.class || 'Exception'}: ${c.message || ''}`;
                    case 'command':
                        return `spark ${c.name || ''}`;
                    case 'job':
                        return c.name || 'Queue Job';
                    case 'cache':
                        return `[${c.action || ''}] ${c.key || ''}`;
                    case 'event':
                        return c.name || 'Event';
                    case 'gate':
                        return `Gate: ${c.ability || ''} (${c.result ? 'Allowed' : 'Denied'})`;
                    case 'http_client':
                        return `${c.method || 'GET'} ${c.url || ''}`;
                    case 'log':
                        return `[${c.level || ''}] ${c.message || ''}`;
                    case 'mail':
                        return `Mail to ${(c.to || []).join(', ')}: ${c.subject || ''}`;
                    case 'model':
                        return `${c.model || ''} (${c.action || ''})`;
                    case 'view':
                        return `View: ${c.template || c.path || ''}`;
                    default:
                        return JSON.stringify(c).substring(0, 80);
                }
            },

            formatTime(iso) {
                if (!iso) return '';
                const d = new Date(iso);
                return d.toLocaleTimeString();
            }
        };
    }
</script>
