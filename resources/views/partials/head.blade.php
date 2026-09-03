<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon.png" type="image/png">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
<link rel="manifest" href="{{ route('manifest') }}">
<meta name="theme-color" content="#09090b">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="{{ config('app.name') }}">

@fonts

@include('partials.reverb-config')

@vite(['resources/css/app.css', 'resources/js/app.js'])

<link rel="stylesheet" href="{{ asset('css/cursors.css') }}">
@fluxAppearance(['nonce' => Illuminate\Support\Facades\Vite::cspNonce()])
