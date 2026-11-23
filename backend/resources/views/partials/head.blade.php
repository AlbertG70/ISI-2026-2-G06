{{-- Cabecera HTML común a todos los layouts. --}}
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#1f2937">
<meta name="color-scheme" content="light">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="DAGAZ">
<link rel="manifest" href="/manifest.webmanifest">
<title>{{ trim($__env->yieldContent('titulo')) ? trim($__env->yieldContent('titulo')).' · ' : '' }}DAGAZ</title>
@vite(['resources/css/app.css', 'resources/js/app.js'])
