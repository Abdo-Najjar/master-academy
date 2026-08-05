<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="{{ $description ?? __('Master Academy, an accredited vocational training center offering practical programs that prepare trainees for the job market.') }}">
    <meta name="theme-color" content="#ffffff">
    <title>{{ $title ?? \App\Support\AppBranding::appName() }}</title>

    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/light/favicon-32x32.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/light/apple-touch-icon.png') }}">

    @vite(['resources/css/site.css', 'resources/js/site.js'])
    @livewireStyles
</head>
<body>
    <a class="skip-link" href="#main">{{ __('Skip to content') }}</a>

    {{ $slot }}

    @livewireScripts
</body>
</html>
