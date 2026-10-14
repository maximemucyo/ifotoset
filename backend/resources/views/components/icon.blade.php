@props(['name', 'strokeWidth' => 2])
@php($paths = \App\Support\Icons::get($name))
@if($paths !== null)
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="{{ $strokeWidth }}" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false" {{ $attributes->merge(['class' => 'size-4 shrink-0']) }}>{!! $paths !!}</svg>
@endif
