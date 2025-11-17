<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>Mailbox for Laravel</title>
    
    {{ Vite::useHotFile('vendor/mailbox/mailbox.hot')
        ->useBuildDirectory("vendor/mailbox")
        ->withEntryPoints(['resources/js/dashboard.js']) }}
    
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
