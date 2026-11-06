@props([
    'server' => [],
    'path' => '/pulse',
    'activeTab' => 'dashboard',
    'counts' => [],
])

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
        };
    </script>
    <style>
        body { font-family: 'Figtree', sans-serif; }
        [x-cloak] { display: none !important; }
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #030712; }
        ::-webkit-scrollbar-thumb { background: #1f2937; border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: #374151; }
    </style>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-950 text-gray-100 min-h-screen antialiased flex flex-col" x-data="pulseApp()" x-init="init()">
    {{-- Navigation Header Component --}}
    <x-pulse::header :server="$server" :path="$path" />

    {{-- Main Page Content --}}
    {{ $slot }}

    {{-- Entry Detail & Correlated Trace Drawer Component --}}
    <x-pulse::drawer />

    {{-- Alpine Reactive Component Script --}}
    <x-pulse::script :counts="$counts" :server="$server" :active-tab="$activeTab" :path="$path" />
</body>
</html>
