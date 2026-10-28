<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title') — {{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/pm.css') }}?v=1">
</head>
<body>
<main class="guest">
    @yield('content')
</main>
</body>
</html>
