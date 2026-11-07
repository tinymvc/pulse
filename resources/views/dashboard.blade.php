<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $entry ? 'Entry details' : ($overview ? 'Overview' : array_merge(...array_values($groups))[$tab]) }} · Spark Pulse</title>
    <script>
        (function() {
            var theme;
            try {
                theme = localStorage.getItem('pulse:theme');
            } catch (e) {}
            if (theme !== 'light' && theme !== 'dark') {
                theme = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' :
                    'light';
            }
            document.documentElement.dataset.theme = theme;
        })();
    </script>
    @include('pulse::styles')
</head>

<body>
    <a href="#main" class="skip">Skip to activity</a>
    @include(
        'pulse::workspace',
        compact(
            'tab',
            'overview',
            'groups',
            'counts',
            'server',
            'entry',
            'trace',
            'languageLoads',
            'page',
            'search',
            'path',
            'filters',
            'recording'))
    <p class="notice" id="notice" role="status" aria-live="polite" hidden></p>
    @include('pulse::script')
</body>

</html>
