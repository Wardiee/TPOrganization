<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
<meta name="theme-color" content="#1C2566" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name') : config('app.name') }}
</title>

{{-- Installable app (PWA): manifest, icons and iPhone "Add to Home Screen" --}}
<link rel="manifest" href="/manifest.webmanifest">
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/icons/favicon-32.png" type="image/png" sizes="32x32">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="TP Admin">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="application-name" content="Tropang Potchi">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
