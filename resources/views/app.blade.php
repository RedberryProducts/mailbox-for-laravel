<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ $data['csrfToken'] ?? csrf_token() }}">
    <title>{{ $data['title'] ?? 'Mailbox for Laravel' }}</title>

    {{ Vite::useHotFile('vendor/mailbox/mailbox.hot')
        ->useBuildDirectory("vendor/mailbox")
        ->withEntryPoints(['resources/js/dashboard.js']) }}
</head>
<body>
    <div id="mailbox-app"></div>
    <script id="mailbox-data" type="application/json">@json($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)</script>
</body>
</html>
