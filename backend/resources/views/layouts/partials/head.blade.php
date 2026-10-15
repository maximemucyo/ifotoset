{{-- Shared <head> essentials for every layout. --}}
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="color-scheme" content="light dark">
<meta name="theme-color" content="#fdf8f4" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#131316" media="(prefers-color-scheme: dark)">
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
<link rel="icon" href="{{ asset('favicon-64.png') }}" type="image/png" sizes="64x64">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('site.webmanifest') }}">
@include('layouts.partials.theme-script')
<x-google-tag />
