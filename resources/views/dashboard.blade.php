<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $entry ? 'Entry details' : ($overview ? 'Overview' : ucfirst($tab)) }} · Spark Pulse</title>
    @include('pulse::styles')
</head>
<body>
    <a href="#main" class="skip">Skip to activity</a>
    @include('pulse::workspace', compact('tab', 'overview', 'groups', 'counts', 'server', 'entry', 'trace', 'languageLoads', 'page', 'search', 'path', 'filters', 'recording'))
    <p class="notice" id="notice" role="status" hidden></p>
    @include('pulse::script')
</body>
</html>
