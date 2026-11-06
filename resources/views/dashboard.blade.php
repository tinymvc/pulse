<x-pulse::layout :server="$server" :path="$path" :active-tab="$activeTab" :counts="$counts">
    <div class="flex-1 flex overflow-hidden">
        {{-- Sidebar Component --}}
        <x-pulse::sidebar :counts="$counts" :active-tab="$activeTab" />

        {{-- Main Content Area --}}
        <main class="flex-1 overflow-y-auto p-6 bg-gray-950">
            {{-- Overview Tab: Server Performance & Module Summary --}}
            <div x-show="activeTab === 'dashboard'">
                <x-pulse::metrics :server="$server" :counts="$counts" />
            </div>

            {{-- Activity Tab: Filterable Entries Table --}}
            <div x-show="activeTab !== 'dashboard'">
                <x-pulse::table :active-tab="$activeTab" />
            </div>
        </main>
    </div>
</x-pulse::layout>
