<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Mailbox for Laravel</title>
    @vite('resources/js/mailbox.js', 'vendor/mailbox')
</head>
<body>
<div id="app"></div>

<script id="mailbox-props" type="application/json">
    {!! json_encode(
        $data,
        JSON_THROW_ON_ERROR
        | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    ) !!}
</script>
</body>
</html>
