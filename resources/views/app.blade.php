<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $page['props']['auth']['lang'] ?? substr(app()->getLocale(), 0, 2)) }}" >
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'Wazely ERP') }}</title>
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ config('app.name', 'Wazely ERP') }}">
        <meta property="og:title" content="{{ config('app.name', 'Wazely ERP') }}">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta name="twitter:card" content="summary">
        <meta name="twitter:title" content="{{ config('app.name', 'Wazely ERP') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        <!-- Scripts -->
        <script src="{{ asset('js/jquery.min.js') }}"></script>
        @routes
        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx'])
        @inertiaHead
        <link rel="icon" href="data:,">
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
