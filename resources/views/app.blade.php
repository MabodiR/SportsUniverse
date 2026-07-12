<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0D1B2A">
    <meta name="application-name" content="SportUniverse">
    <meta name="apple-mobile-web-app-title" content="SportUniverse">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="icon" href="/images/logo/favicon.ico" sizes="any">
    <link rel="icon" type="image/svg+xml" href="/images/logo/sportuniverse-icon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="/images/logo/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/images/logo/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/images/logo/favicon-180x180.png">
    <link rel="manifest" href="/images/logo/site.webmanifest">
    <title inertia>{{ config('app.name', 'SportUniverse') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.ts'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
