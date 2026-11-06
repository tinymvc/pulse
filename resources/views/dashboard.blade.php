@extends('pulse::layout')

@section('content')
<div class="flex-1 flex overflow-hidden">
    {{-- Sidebar Navigation --}}
    @include('pulse::partials.sidebar', ['counts' => $counts, 'activeTab' => $activeTab])

    {{-- Main Content Area --}}
    <main class="flex-1 overflow-y-auto p-6 bg-gray-950">
        {{-- Overview Tab: Server Performance & Module Summary --}}
        <div x-show="activeTab === 'dashboard'">
            @include('pulse::partials.metrics', ['server' => $server, 'counts' => $counts])
        </div>

        {{-- Module Entries & Filter Table --}}
        <div x-show="activeTab !== 'dashboard'">
            @include('pulse::partials.table', ['activeTab' => $activeTab])
        </div>
    </main>
</div>
@endsection
