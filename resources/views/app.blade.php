<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @php
        $seoUrl = url()->current();
        $seoTitle = $seo['title'] ?? config('seo.title');
        $seoDescription = $seo['description'] ?? config('seo.description');
        $seoImage = $seo['image'] ?? url(config('seo.image'));
        $noIndex = auth()->check() || request()->routeIs('login', 'register', 'password.*', 'phone-auth', 'social-auth', 'verification.*');
    @endphp
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0D1B2A">
    <meta name="color-scheme" content="light">
    <meta name="application-name" content="SportsUniverse">
    <meta name="apple-mobile-web-app-title" content="SportsUniverse">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="format-detection" content="telephone=no">
    <meta name="description" content="{{ $seoDescription }}">
    <meta name="keywords" content="{{ config('seo.keywords') }}">
    <meta name="author" content="SportsUniverse">
    <meta name="robots" content="{{ $noIndex ? 'noindex, nofollow, noarchive' : 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1' }}">
    <meta name="googlebot" content="{{ $noIndex ? 'noindex, nofollow' : 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1' }}">
    <link rel="canonical" href="{{ $seoUrl }}">
    <link rel="alternate" hreflang="en-ZA" href="{{ $seoUrl }}">
    <meta property="og:locale" content="en_ZA">
    <meta property="og:type" content="{{ $seo['type'] ?? 'website' }}">
    <meta property="og:site_name" content="SportsUniverse">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:description" content="{{ $seoDescription }}">
    <meta property="og:url" content="{{ $seoUrl }}">
    <meta property="og:image" content="{{ $seoImage }}">
    <meta property="og:image:alt" content="SportsUniverse — Talent. Opportunity. Community.">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $seoTitle }}">
    <meta name="twitter:description" content="{{ $seoDescription }}">
    <meta name="twitter:image" content="{{ $seoImage }}">
    @if(config('seo.twitter_handle'))<meta name="twitter:site" content="{{ config('seo.twitter_handle') }}">@endif
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth<meta name="user-id" content="{{ auth()->id() }}">@endauth
    @if(config('push.public_key'))<meta name="vapid-public-key" content="{{ config('push.public_key') }}">@endif
    <link rel="icon" href="/images/logo/favicon.ico" sizes="any">
    <link rel="icon" type="image/svg+xml" href="/images/logo/sportuniverse-icon.svg">
    <link rel="icon" type="image/png" sizes="32x32" href="/images/logo/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/images/logo/favicon-16x16.png">
    <link rel="apple-touch-icon" sizes="180x180" href="/images/logo/favicon-180x180.png">
    <link rel="manifest" href="/images/logo/site.webmanifest">
    <script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => 'SportsUniverse', 'url' => config('app.url'), 'logo' => url('/images/logo/sportuniverse-icon-1024.png'), 'description' => $seoDescription], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    <script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => 'SportsUniverse', 'url' => config('app.url')], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    <title inertia>{{ $seoTitle }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.ts'])
    @inertiaHead
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to main content</a>
    @inertia
</body>
</html>
