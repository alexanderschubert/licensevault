<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="icon" type="image/x-icon" sizes="32x32" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192-v3.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/icons/apple-touch-icon-v3.png">
    <meta name="theme-color" content="#f5f6fa" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0d0e17" media="(prefers-color-scheme: dark)">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="LicenseVault">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="robots" content="noindex,nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'LicenseVault') · LicenseVault</title>
    <script>
        (function () {
            try {
                var m = localStorage.getItem('lv-theme') || 'system';
                if (m === 'dark' || (m === 'system' && matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.classList.add('dark');
                }
            } catch (e) {}
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body x-data="theme" x-init="init()">
    @yield('body')
</body>
</html>
